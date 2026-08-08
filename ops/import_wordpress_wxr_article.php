<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Media;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostMeta;
use Modules\Blog\Models\Tag;
use Modules\Blog\Services\SeoScoreCalculator;

require getcwd().'/vendor/autoload.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourceFile = $argv[1] ?? '';
$wordpressId = $argv[2] ?? '';
$updateExisting = in_array('--update', $argv, true);

if ($sourceFile === '' || $wordpressId === '') {
    fwrite(STDERR, "Usage: php ops/import_wordpress_wxr_article.php <wxr-file> <wordpress-post-id> [--update]\n");
    exit(2);
}

if (! is_file($sourceFile)) {
    fwrite(STDERR, "WXR file not found: {$sourceFile}\n");
    exit(2);
}

libxml_use_internal_errors(true);
$wxr = simplexml_load_file($sourceFile, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
libxml_clear_errors();

if (! $wxr) {
    fwrite(STDERR, "Unable to parse WXR file.\n");
    exit(2);
}

$item = null;
$attachments = [];

foreach ($wxr->channel->item as $candidate) {
    $candidateWp = $candidate->children('http://wordpress.org/export/1.2/');
    $candidateId = (string) $candidateWp->post_id;

    if ((string) $candidateWp->post_type === 'attachment') {
        $attachments[$candidateId] = $candidate;
    }

    if ($candidateId === $wordpressId && (string) $candidateWp->post_type === 'post') {
        $item = $candidate;
    }
}

if (! $item instanceof SimpleXMLElement) {
    fwrite(STDERR, "WordPress post {$wordpressId} was not found.\n");
    exit(2);
}

$wp = $item->children('http://wordpress.org/export/1.2/');

if ((string) $wp->status !== 'publish') {
    fwrite(STDERR, "WordPress post {$wordpressId} is not published.\n");
    exit(2);
}

$title = normalizeTitle(plainText((string) $item->title));
if ($title === '') {
    fwrite(STDERR, "SKIP_EMPTY_TITLE: WordPress post {$wordpressId} has no title.\n");
    exit(5);
}

$existing = Post::withTrashed()
    ->whereHas('metas', fn ($query) => $query->where('meta_key', 'wordpress_wxr_id')->where('meta_value', $wordpressId))
    ->first();

if ($existing && ! $updateExisting) {
    echo json_encode([
        'result' => 'skipped_existing',
        'id' => $existing->id,
        'wordpress_id' => $wordpressId,
        'title' => $existing->title,
        'url' => url('/blog/'.$existing->slug),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(3);
}

$oldCategories = wordpressTerms($item, 'category');
$oldTags = wordpressTerms($item, 'post_tag');
$sourceUrl = (string) $item->link;
$rawContent = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
$content = adaptArticleHtml(cleanWordPressHtml($rawContent), $wordpressId);
$sourceExcerpt = plainText((string) $item->children('http://wordpress.org/export/1.2/excerpt/')->encoded);
$author = User::query()->where('name', 'ziad.mansor')->firstOrFail();
$publishedAt = Carbon::parse((string) ($wp->post_date_gmt ?: $wp->post_date), 'UTC');
$slugBase = Str::slug(rawurldecode((string) $wp->post_name)) ?: Str::slug($title) ?: 'wordpress-'.$wordpressId;
$slug = $existing?->slug ?: uniquePostSlug($slugBase);
$editorial = editorialFor($wordpressId, $title, $slug, $content, $sourceExcerpt, $oldCategories, $oldTags);
$warnings = [];

[$content, $inlineMedia] = importContentImages(
    $content,
    $wordpressId,
    $title,
    $author->id,
    $warnings,
);

if (plainText($content) === '') {
    $content = '<p>'.htmlspecialchars($editorial['excerpt'], ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
    $warnings[] = 'Source article had no body; a factual archival summary was added.';
} elseif ($wordpressId !== '3040' && ! str_starts_with(plainText($content), $editorial['excerpt'])) {
    $content = '<p class="article-intro">'.htmlspecialchars($editorial['excerpt'], ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>'.$content;
}

$thumbnailId = wordpressMeta($wp, '_thumbnail_id');
$featuredMedia = null;

if ($thumbnailId !== '' && isset($attachments[$thumbnailId])) {
    $attachmentWp = $attachments[$thumbnailId]->children('http://wordpress.org/export/1.2/');
    $imageUrl = trim((string) $attachmentWp->attachment_url);
    if ($imageUrl !== '') {
        $featuredMedia = importImage($imageUrl, $wordpressId, $editorial['image_alt'], $title, $author->id, true);
        if (! $featuredMedia) {
            $warnings[] = 'Featured image download failed: '.$imageUrl;
        }
    }
}

$featuredMedia ??= collect($inlineMedia)->first(fn (Media $media): bool => str_starts_with((string) $media->mime_type, 'image/'));

if (! $featuredMedia) {
    $warnings[] = 'No usable featured image exists in the source export.';
} else {
    $content = replaceUnresolvedImagesWithFeatured($content, $featuredMedia, $title, $warnings);
}

$post = DB::transaction(function () use (
    $editorial,
    $title,
    $slug,
    $content,
    $publishedAt,
    $author,
    $featuredMedia,
    $sourceUrl,
    $wordpressId,
    $item,
    $existing,
    $oldCategories,
    $oldTags,
): Post {
    $category = Category::query()->firstOrCreate(
        ['slug' => $editorial['category']['slug']],
        ['name' => $editorial['category']['name'], 'description' => $editorial['category']['description']],
    );

    $tagIds = collect($editorial['tags'])->map(function (array $tag): int {
        return Tag::query()->firstOrCreate(['slug' => $tag['slug']], ['name' => $tag['name']])->id;
    })->all();

    $canonicalUrl = url('/blog/'.$slug);
    $seoAttributes = [
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'seo_title' => $editorial['seo_title'],
        'seo_description' => $editorial['seo_description'],
        'focus_keyword' => $editorial['focus_keyword'],
        'featured_image_id' => $featuredMedia?->id,
        'category_id' => $category->id,
    ];
    $seoScore = app(SeoScoreCalculator::class)->calculate($seoAttributes, implode(',', $tagIds));

    $post = $existing ?: new Post;
    if ($post->trashed()) {
        $post->restore();
    }
    $post->fill([
        'category_id' => $category->id,
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $editorial['excerpt'],
        'content' => $content,
        'status' => 'published',
        'visibility' => 'public',
        'featured_image' => $featuredMedia?->url,
        'featured_image_id' => $featuredMedia?->id,
        'featured_image_alt' => $featuredMedia ? $editorial['image_alt'] : null,
        'layout_template' => 'default',
        'template' => 'default',
        'layout' => 'default',
        'meta_title' => $editorial['seo_title'],
        'meta_description' => $editorial['seo_description'],
        'seo_title' => $editorial['seo_title'],
        'seo_description' => $editorial['seo_description'],
        'seo_focus_keyword' => $editorial['focus_keyword'],
        'focus_keyword' => $editorial['focus_keyword'],
        'seo_score' => $seoScore,
        'seo_schema_type' => 'Article',
        'schema_type' => 'Article',
        'seo_social_title' => $editorial['seo_title'],
        'seo_social_description' => $editorial['seo_description'],
        'canonical_url' => $canonicalUrl,
        'robots_index' => true,
        'robots_follow' => true,
        'published_at' => $publishedAt,
        'scheduled_at' => null,
        'author_id' => $author->id,
        'created_by' => $author->id,
        'updated_by' => $author->id,
    ]);
    $post->save();
    $post->categories()->sync([$category->id]);
    $post->tags()->sync($tagIds);

    $sourceAuthor = plainText((string) $item->children('http://purl.org/dc/elements/1.1/')->creator);
    foreach ([
        'wordpress_wxr_id' => $wordpressId,
        'wordpress_source_url' => $sourceUrl,
        'wordpress_source_author' => $sourceAuthor,
        'wordpress_source_categories' => json_encode($oldCategories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'wordpress_source_tags' => json_encode($oldTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ] as $key => $value) {
        PostMeta::query()->updateOrCreate(
            ['post_id' => $post->id, 'meta_key' => $key],
            ['meta_value' => $value],
        );
    }

    return $post->fresh(['author', 'category', 'tags', 'featuredImage']);
});

echo json_encode([
    'result' => $existing ? 'updated' : 'imported',
    'id' => $post->id,
    'wordpress_id' => $wordpressId,
    'title' => $post->title,
    'url' => url('/blog/'.$post->slug),
    'status' => $post->status,
    'published_at' => $post->published_at?->toDateTimeString(),
    'author' => $post->author?->name,
    'category' => $post->category?->name,
    'tags' => $post->tags->pluck('name')->all(),
    'featured_image' => $post->featuredImage?->url,
    'inline_images' => count($inlineMedia),
    'seo_title' => $post->seo_title,
    'seo_description' => $post->seo_description,
    'focus_keyword' => $post->focus_keyword,
    'seo_score' => $post->seo_score,
    'warnings' => $warnings,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

function plainText(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
}

function normalizeTitle(string $title): string
{
    $title = trim($title, " \t\n\r\0\x0B\"“”");
    $title = str_replace(['الاسلامي', 'الاردن', 'الاردني', 'الاردنية'], ['الإسلامي', 'الأردن', 'الأردني', 'الأردنية'], $title);

    return preg_replace('/\s+/u', ' ', $title) ?: $title;
}

function limitText(string $value, int $limit): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    if (mb_strlen($value) <= $limit) {
        return $value;
    }

    $short = mb_substr($value, 0, $limit - 1);
    $lastSpace = mb_strrpos($short, ' ');

    return rtrim($lastSpace === false ? $short : mb_substr($short, 0, $lastSpace), " ،؛:-").'…';
}

/** @return array<int, string> */
function wordpressTerms(SimpleXMLElement $item, string $domain): array
{
    $terms = [];
    foreach ($item->category as $category) {
        if ((string) $category['domain'] === $domain) {
            $name = plainText((string) $category);
            if ($name !== '') {
                $terms[] = $name;
            }
        }
    }

    return array_values(array_unique($terms));
}

function wordpressMeta(SimpleXMLElement $wp, string $key): string
{
    foreach ($wp->postmeta as $meta) {
        if ((string) $meta->meta_key === $key) {
            return trim((string) $meta->meta_value);
        }
    }

    return '';
}

/** @return array<string, mixed> */
function editorialFor(string $wordpressId, string $title, string $slug, string $content, string $sourceExcerpt, array $oldCategories, array $oldTags): array
{
    if ($wordpressId === '3040') {
        return [
            'category' => categoryDefinition('visual-culture'),
            'tags' => tagsFromNames(['الخزف الإسلامي', 'الفن الإسلامي', 'تاريخ الفن']),
            'seo_title' => 'الخزف الإسلامي: تاريخه وتقنياته وزخارفه الفنية',
            'seo_description' => 'اكتشف تاريخ الخزف الإسلامي وتطور تقنياته وزخارفه، من مراكز صناعته الأولى إلى أبرز الأساليب التي ميزته في العالم الإسلامي عبر العصور.',
            'focus_keyword' => 'الخزف الإسلامي',
            'excerpt' => 'رحلة في تاريخ الخزف الإسلامي، وتقنيات صناعته وزخارفه، وأهم المراكز والمدارس التي أسهمت في تطوره عبر العصور.',
            'image_alt' => 'قطعة مزخرفة من الخزف الإسلامي',
        ];
    }

    $category = chooseCategory($title, $content, $oldCategories);
    $focusKeyword = focusKeyword($title, $slug);
    $bodyText = plainText($content);
    $bodySummary = summaryFromHtml($content);
    $excerpt = $sourceExcerpt !== '' ? limitText($sourceExcerpt, 180) : limitText($bodySummary ?: $bodyText, 180);
    if ($excerpt === '') {
        $excerpt = 'توثيق أرشيفي من Art INPA بعنوان «'.$title.'»، محفوظ ضمن أخبار ومواد الفنون والثقافة البصرية.';
    }
    if (! Str::contains(Str::lower($excerpt), Str::lower($focusKeyword))) {
        $excerpt = limitText($focusKeyword.' — '.$excerpt, 180);
    }

    $descriptionBase = Str::startsWith(Str::lower($excerpt), Str::lower($focusKeyword)) ? $excerpt : $focusKeyword.' — '.$excerpt;
    if (mb_strlen($descriptionBase) < 120) {
        $descriptionBase .= ' اقرأ التفاصيل والسياق الكامل ضمن أرشيف Art INPA للفنون والثقافة البصرية.';
    }

    $seoTitle = $title;
    if (mb_strlen($seoTitle) < 35) {
        $seoTitle .= match ($category['slug']) {
            'artists' => ' – سيرة ومسيرة فنية | Art INPA',
            'exhibitions' => ' – معرض وفنون بصرية | Art INPA',
            'art-news' => ' – أخبار الفن والثقافة | Art INPA',
            'art-therapy' => ' – العلاج بالفن | Art INPA',
            default => ' – فنون وثقافة بصرية | Art INPA',
        };
    }

    return [
        'category' => $category,
        'tags' => deriveTags($title, $bodyText, $oldCategories, $oldTags, $category),
        'seo_title' => limitText($seoTitle, 65),
        'seo_description' => limitText($descriptionBase, 160),
        'focus_keyword' => $focusKeyword,
        'excerpt' => $excerpt,
        'image_alt' => 'الصورة البارزة لمقالة «'.$title.'»',
    ];
}

/** @return array{name: string, slug: string, description: string} */
function categoryDefinition(string $slug): array
{
    return match ($slug) {
        'artists' => ['name' => 'فنانون', 'slug' => 'artists', 'description' => 'سير وتجارب الفنانين والشخصيات الثقافية.'],
        'exhibitions' => ['name' => 'معارض', 'slug' => 'exhibitions', 'description' => 'أخبار وتغطيات المعارض والفعاليات الفنية.'],
        'art-criticism' => ['name' => 'نقد فني', 'slug' => 'art-criticism', 'description' => 'قراءات ودراسات في الفن والثقافة.'],
        'art-market' => ['name' => 'سوق الفن', 'slug' => 'art-market', 'description' => 'المزادات والاقتناء والاستثمار في الفن.'],
        'art-news' => ['name' => 'أخبار فنية', 'slug' => 'art-news', 'description' => 'أخبار الفن والثقافة وفعاليات Art INPA.'],
        'art-therapy' => ['name' => 'العلاج بالفن', 'slug' => 'art-therapy', 'description' => 'مقالات ومبادرات ودراسات العلاج بالفن.'],
        'art-initiatives' => ['name' => 'مبادرات فنية', 'slug' => 'art-initiatives', 'description' => 'مبادرات ومشروعات Art INPA الفنية والمجتمعية.'],
        default => ['name' => 'ثقافة بصرية', 'slug' => 'visual-culture', 'description' => 'مقالات في تاريخ الفن والثقافة البصرية.'],
    };
}

function chooseCategory(string $title, string $content, array $oldCategories): array
{
    $haystack = Str::lower($title.' '.implode(' ', $oldCategories));

    if (Str::contains($haystack, ['العلاج بالفن', 'العلاج بالموسيقى', 'art therapy'])) {
        return categoryDefinition('art-therapy');
    }
    if (Str::contains($haystack, ['معرض', 'معارض', 'gallery', 'مهرجان', 'سمبوزيوم', 'سمبوزيم'])) {
        return categoryDefinition('exhibitions');
    }
    if (Str::contains($haystack, ['مزاد', 'الاستثمار', 'سوق الفن', 'اقتناء'])) {
        return categoryDefinition('art-market');
    }
    if (array_intersect($oldCategories, ['advisory board', 'Arbitration Committees', 'Administration', 'Featured Members', 'Important members in our platform', 'honorary members', 'Introduction to an artist'])) {
        return categoryDefinition('artists');
    }
    if (Str::contains($haystack, ['نقد', 'قراءة', 'لماذا غابت', 'العمل الفني لا يقدم'])) {
        return categoryDefinition('art-criticism');
    }
    if (Str::contains($haystack, ['مبادرة', 'حاضنة', 'مذكرة تعاون', 'مذكرة تفاهم', 'شبكة الفنون'])) {
        return categoryDefinition('art-initiatives');
    }
    if (array_intersect($oldCategories, ['news', 'Good News', 'Main cover news', 'Art INPA'])) {
        return categoryDefinition('art-news');
    }

    return categoryDefinition('visual-culture');
}

function focusKeyword(string $title, string $slug): string
{
    $cleanTitle = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title) ?: $title);
    $words = array_values(array_filter(
        preg_split('/\s+/u', $cleanTitle) ?: [],
        fn (string $word): bool => mb_strlen($word) > 1,
    ));

    for ($length = min(4, count($words)); $length >= 2; $length--) {
        for ($offset = 0; $offset <= count($words) - $length; $offset++) {
            $candidate = implode(' ', array_slice($words, $offset, $length));
            $candidateSlug = Str::slug($candidate);
            if ($candidateSlug !== '' && Str::contains($slug, $candidateSlug)) {
                return $candidate;
            }
        }
    }

    return implode(' ', array_slice($words, 0, min(3, count($words)))) ?: $title;
}

/** @return array<int, array{name: string, slug: string}> */
function deriveTags(string $title, string $content, array $oldCategories, array $oldTags, array $category): array
{
    $haystack = Str::lower($title.' '.mb_substr($content, 0, 3000));
    $tags = [$category['name']];
    $subjects = [
        'الفن التشكيلي' => ['الفن التشكيلي'],
        'الفن الإسلامي' => ['الفن الإسلامي', 'اسلامي', 'إسلامي'],
        'الخزف' => ['الخزف', 'الفخار'],
        'العلاج بالفن' => ['العلاج بالفن', 'العلاج بالموسيقى'],
        'الرسم' => ['الرسم', 'لوحات'],
        'النحت' => ['نحت', 'منحوت'],
        'فن معاصر' => ['الفن المعاصر', 'فن معاصر'],
        'تاريخ الفن' => ['تاريخ الفن', 'مدارس الفن', 'الحركات الفنية'],
        'فنانون عرب' => ['فنان', 'فنانة'],
        'Art INPA' => ['شبكة الفنون', 'art inpa', 'inpa'],
        'الأردن' => ['الأردن', 'الأردني', 'عمان', 'إربد'],
        'فلسطين' => ['فلسطين', 'غزة'],
    ];

    foreach ($subjects as $name => $needles) {
        if (Str::contains($haystack, $needles)) {
            $tags[] = $name;
        }
    }

    return tagsFromNames(array_slice(array_values(array_unique($tags)), 0, 5));
}

function summaryFromHtml(string $html): string
{
    if ($html === '') {
        return '';
    }

    $document = htmlDocument($html, 'summary-root');
    $xpath = new DOMXPath($document);
    foreach (iterator_to_array($xpath->query('//p|//li') ?: []) as $node) {
        $text = plainText($node->textContent);
        preg_match_all('/[\x{0600}-\x{06FF}]/u', $text, $arabicLetters);
        preg_match_all('/[\x{0600}-\x{06FF}]/u', mb_substr($text, 0, 35), $arabicPrefix);
        if (mb_strlen($text) >= 90 && count($arabicLetters[0] ?? []) >= 45 && count($arabicPrefix[0] ?? []) >= 15) {
            return $text;
        }
    }

    return plainText($html);
}

/** @return array<int, array{name: string, slug: string}> */
function tagsFromNames(array $names): array
{
    return array_map(function (string $name): array {
        $knownSlugs = [
            'ثقافة بصرية' => 'visual-culture',
            'فنانون' => 'artists',
            'معارض' => 'exhibitions',
            'أخبار فنية' => 'art-news',
            'مبادرات فنية' => 'art-initiatives',
            'العلاج بالفن' => 'art-therapy',
            'الفن التشكيلي' => 'visual-arts',
            'الفن الإسلامي' => 'islamic-art',
            'الخزف' => 'ceramics',
            'الخزف الإسلامي' => 'islamic-ceramics',
            'تاريخ الفن' => 'art-history',
            'فنانون عرب' => 'arab-artists',
            'Art INPA' => 'art-inpa',
            'الأردن' => 'jordan',
            'فلسطين' => 'palestine',
        ];

        return ['name' => $name, 'slug' => $knownSlugs[$name] ?? (Str::slug($name) ?: 'tag-'.substr(sha1($name), 0, 10))];
    }, $names);
}

function cleanWordPressHtml(string $html): string
{
    $document = htmlDocument($html, 'import-root');
    $xpath = new DOMXPath($document);

    foreach (['//script', '//style', '//iframe', '//object', '//embed', '//form', '//input', '//button', '//noscript'] as $query) {
        foreach (iterator_to_array($xpath->query($query) ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    foreach (iterator_to_array($xpath->query('//*') ?: []) as $node) {
        if (! $node instanceof DOMElement) {
            continue;
        }
        foreach (iterator_to_array($node->attributes ?: []) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if ($name === 'style' || str_starts_with($name, 'on') || str_starts_with($name, 'data-') || in_array($name, ['srcset', 'sizes'], true)) {
                $node->removeAttribute($attribute->name);
            } elseif (in_array($name, ['href', 'src'], true) && preg_match('/^javascript:/i', $value)) {
                $node->removeAttribute($attribute->name);
            }
        }
    }

    foreach (iterator_to_array($xpath->query('//p[not(normalize-space()) and not(*)]') ?: []) as $node) {
        $node->parentNode?->removeChild($node);
    }
    foreach (iterator_to_array($xpath->query('//a[contains(@href, "action=edit") or contains(@href, "redlink=1")]') ?: []) as $link) {
        $link->parentNode?->replaceChild($document->createTextNode($link->textContent), $link);
    }
    foreach (iterator_to_array($xpath->query('//a[starts-with(@href, "http://") or starts-with(@href, "https://")]') ?: []) as $link) {
        if ($link instanceof DOMElement) {
            $link->setAttribute('rel', 'noopener noreferrer');
        }
    }

    return documentInnerHtml($document, 'import-root');
}

function adaptArticleHtml(string $html, string $wordpressId): string
{
    if ($wordpressId !== '3040') {
        return $html;
    }

    $document = htmlDocument($html, 'article-root');
    $xpath = new DOMXPath($document);
    $headings = ['التصوير الإسلامي', 'المنمنمات وتزيين المخطوطات', 'المخطوطات المصورة', 'الخط العربي', 'المنسوجات الإسلامية', 'الطنافس الإسلامية', 'أشكال الفن الإسلامي'];

    foreach (iterator_to_array($xpath->query('//p') ?: []) as $paragraph) {
        $text = trim(preg_replace('/\s+/u', ' ', $paragraph->textContent) ?: '');
        if (in_array($text, $headings, true)) {
            $heading = $document->createElement('h2');
            $heading->appendChild($document->createTextNode($text));
            $paragraph->parentNode?->replaceChild($heading, $paragraph);
        } elseif (str_starts_with($text, 'الموقع:') || str_starts_with($text, 'الرابط :')) {
            $paragraph->parentNode?->removeChild($paragraph);
        }
    }

    $source = $document->createElement('p');
    $source->setAttribute('class', 'article-source');
    $source->appendChild($document->createTextNode('المصدر: '));
    $sourceLink = $document->createElement('a', 'المعرفة');
    $sourceLink->setAttribute('href', 'https://m.marefa.org/%D9%81%D9%86_%D8%A5%D8%B3%D9%84%D8%A7%D9%85%D9%8A');
    $sourceLink->setAttribute('rel', 'noopener noreferrer');
    $source->appendChild($sourceLink);
    $document->getElementById('article-root')?->appendChild($source);

    return documentInnerHtml($document, 'article-root');
}

/** @return array{0: string, 1: array<int, Media>} */
function importContentImages(string $html, string $wordpressId, string $title, int $userId, array &$warnings): array
{
    if (! str_contains(Str::lower($html), '<img') && ! str_contains($html, 'art-inpa.com/wp-content/uploads')) {
        return [$html, []];
    }

    $document = htmlDocument($html, 'images-root');
    $xpath = new DOMXPath($document);
    $mediaItems = [];

    foreach (iterator_to_array($xpath->query('//img') ?: []) as $index => $image) {
        if (! $image instanceof DOMElement) {
            continue;
        }
        $source = trim($image->getAttribute('src'));
        if ($source === '') {
            continue;
        }
        if (str_starts_with($source, '//')) {
            $source = 'https:'.$source;
        } elseif (str_starts_with($source, '/')) {
            $source = 'https://art-inpa.com'.$source;
        }
        if (! preg_match('#^https?://(?:www\.)?art-inpa\.com/#i', $source)) {
            continue;
        }

        $alt = plainText($image->getAttribute('alt')) ?: 'صورة توضيحية ضمن مقالة «'.$title.'»';
        $media = importImage($source, $wordpressId, $alt, $title, $userId, false);
        if (! $media) {
            $warnings[] = 'Inline image download failed: '.$source;
            continue;
        }
        $image->setAttribute('src', $media->url);
        $image->setAttribute('alt', $alt);
        $image->removeAttribute('srcset');
        $image->removeAttribute('sizes');
        $image->removeAttribute('data-src');
        $mediaItems[] = $media;
    }

    foreach (iterator_to_array($xpath->query('//a[@href]') ?: []) as $link) {
        if (! $link instanceof DOMElement) {
            continue;
        }
        $source = trim($link->getAttribute('href'));
        if (! preg_match('#^https?://(?:www\.)?art-inpa\.com/wp-content/uploads/#i', $source)) {
            continue;
        }

        $extension = strtolower(pathinfo((string) parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $alt = plainText($link->getAttribute('title')) ?: 'صورة توضيحية ضمن مقالة «'.$title.'»';
            $media = importImage($source, $wordpressId, $alt, $title, $userId, false);
            if (! $media) {
                $warnings[] = 'Linked image download failed: '.$source;
                continue;
            }

            $link->setAttribute('href', $media->url);
            if ($link->getElementsByTagName('img')->length === 0) {
                $image = $document->createElement('img');
                $image->setAttribute('src', $media->url);
                $image->setAttribute('alt', $alt);
                $image->setAttribute('loading', 'lazy');
                $link->appendChild($image);
            }
            $mediaItems[] = $media;
            continue;
        }

        if ($extension === 'mp4') {
            $media = importAsset($source, $wordpressId, $title, $userId);
            if (! $media) {
                $warnings[] = 'Linked video download failed: '.$source;
                continue;
            }

            $link->setAttribute('href', $media->url);
            if (plainText($link->textContent) === '') {
                $video = $document->createElement('video');
                $video->setAttribute('controls', 'controls');
                $video->setAttribute('preload', 'metadata');
                $sourceNode = $document->createElement('source');
                $sourceNode->setAttribute('src', $media->url);
                $sourceNode->setAttribute('type', (string) $media->mime_type);
                $video->appendChild($sourceNode);
                $link->appendChild($video);
            }
            $mediaItems[] = $media;
        }
    }

    $videoPattern = '#(https?://(?:www\.)?art-inpa\.com/wp-content/uploads/[^\s<>"\']+?\.mp4)#i';
    foreach (iterator_to_array($xpath->query('//text()[contains(., "art-inpa.com/wp-content/uploads")]') ?: []) as $textNode) {
        $value = $textNode->nodeValue ?? '';
        if (! preg_match($videoPattern, $value)) {
            continue;
        }

        $parts = preg_split($videoPattern, $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($parts)) {
            continue;
        }

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (! preg_match($videoPattern, $part)) {
                $textNode->parentNode?->insertBefore($document->createTextNode($part), $textNode);
                continue;
            }

            $media = importAsset($part, $wordpressId, $title, $userId);
            if (! $media) {
                $warnings[] = 'Plain-text video download failed: '.$part;
                $textNode->parentNode?->insertBefore($document->createTextNode($part), $textNode);
                continue;
            }

            $video = $document->createElement('video');
            $video->setAttribute('controls', 'controls');
            $video->setAttribute('preload', 'metadata');
            $sourceNode = $document->createElement('source');
            $sourceNode->setAttribute('src', $media->url);
            $sourceNode->setAttribute('type', (string) $media->mime_type);
            $video->appendChild($sourceNode);
            $textNode->parentNode?->insertBefore($video, $textNode);
            $mediaItems[] = $media;
        }
        $textNode->parentNode?->removeChild($textNode);
    }

    $mediaItems = collect($mediaItems)->unique('id')->values()->all();

    return [documentInnerHtml($document, 'images-root'), $mediaItems];
}

function replaceUnresolvedImagesWithFeatured(string $html, Media $featuredMedia, string $title, array &$warnings): string
{
    if (! str_contains($html, 'art-inpa.com/wp-content/uploads')) {
        return $html;
    }

    $document = htmlDocument($html, 'fallback-root');
    $xpath = new DOMXPath($document);
    foreach (iterator_to_array($xpath->query('//img[contains(@src, "art-inpa.com/wp-content/uploads")]') ?: []) as $image) {
        if (! $image instanceof DOMElement) {
            continue;
        }
        $oldSource = $image->getAttribute('src');
        $image->setAttribute('src', $featuredMedia->url);
        $image->setAttribute('alt', 'صورة توضيحية ضمن مقالة «'.$title.'»');
        $warnings[] = 'Unavailable inline image replaced with the article featured image: '.$oldSource;
    }

    return documentInnerHtml($document, 'fallback-root');
}

function htmlDocument(string $html, string $rootId): DOMDocument
{
    $document = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="'.$rootId.'">'.$html.'</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();

    return $document;
}

function documentInnerHtml(DOMDocument $document, string $rootId): string
{
    $root = $document->getElementById($rootId);
    $html = '';
    foreach ($root?->childNodes ?? [] as $node) {
        $html .= $document->saveHTML($node);
    }

    return trim($html);
}

function uniquePostSlug(string $base): string
{
    $base = $base !== '' ? $base : 'wordpress-article';
    $slug = $base;
    $suffix = 2;
    while (Post::withTrashed()->where('slug', $slug)->exists()) {
        $slug = $base.'-'.$suffix++;
    }

    return $slug;
}

function importImage(string $url, string $wordpressId, string $alt, string $title, int $userId, bool $featured): ?Media
{
    $media = importAsset($url, $wordpressId, $title, $userId, $alt, $featured);

    return $media && str_starts_with((string) $media->mime_type, 'image/') ? $media : null;
}

function importAsset(string $url, string $wordpressId, string $title, int $userId, ?string $alt = null, bool $featured = false): ?Media
{
    if (! preg_match('#^https?://(?:www\.)?art-inpa\.com/#i', $url) || substr_count($url, 'http') > 1) {
        return null;
    }

    $hash = sha1($url);
    $existing = Media::query()->where('path', 'like', 'blog/imported/%/'.$hash.'.%')->first();
    if ($existing && Storage::disk('public')->exists($existing->path)) {
        return $existing;
    }

    try {
        $response = Http::withHeaders(['User-Agent' => 'ArtINPA-WXR-Migration/1.0'])
            ->timeout(60)
            ->retry(2, 700)
            ->get($url);
    } catch (Throwable) {
        return null;
    }

    if (! $response->successful() || strlen($response->body()) > 64 * 1024 * 1024) {
        return null;
    }

    $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
    $extension = match ($mime) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        default => null,
    };
    if ($extension === null) {
        return null;
    }

    $path = 'blog/imported/'.$wordpressId.'/'.$hash.'.'.$extension;
    Storage::disk('public')->put($path, $response->body());
    $dimensions = str_starts_with($mime, 'image/') ? @getimagesizefromstring($response->body()) : null;

    return Media::query()->updateOrCreate(['path' => $path], [
        'disk' => 'public',
        'url' => Storage::url($path),
        'mime_type' => $mime,
        'size' => strlen($response->body()),
        'width' => is_array($dimensions) ? ($dimensions[0] ?? null) : null,
        'height' => is_array($dimensions) ? ($dimensions[1] ?? null) : null,
        'alt_text' => $alt,
        'title' => $title,
        'caption' => null,
        'description' => $featured ? 'الصورة البارزة للمقالة المستوردة من موقع Art INPA السابق.' : 'وسائط ضمن محتوى المقالة المستوردة من موقع Art INPA السابق.',
        'uploaded_by' => $userId,
    ]);
}
