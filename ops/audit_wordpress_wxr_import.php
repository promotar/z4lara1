<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Blog\Models\Media;
use Modules\Blog\Models\Post;

require getcwd().'/vendor/autoload.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourceFile = $argv[1] ?? '';
if (! is_file($sourceFile)) {
    fwrite(STDERR, "Usage: php ops/audit_wordpress_wxr_import.php <wxr-file>\n");
    exit(2);
}

libxml_use_internal_errors(true);
$wxr = simplexml_load_file($sourceFile, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
libxml_clear_errors();

$expected = [];
$emptyTitleIds = [];
foreach ($wxr->channel->item as $item) {
    $wp = $item->children('http://wordpress.org/export/1.2/');
    if ((string) $wp->post_type !== 'post' || (string) $wp->status !== 'publish') {
        continue;
    }

    $id = (string) $wp->post_id;
    $title = trim((string) $item->title);
    if ($title === '') {
        $emptyTitleIds[] = $id;
        continue;
    }

    $thumbnailId = '';
    foreach ($wp->postmeta as $meta) {
        if ((string) $meta->meta_key === '_thumbnail_id') {
            $thumbnailId = trim((string) $meta->meta_value);
            break;
        }
    }

    $expected[$id] = [
        'title' => $title,
        'published_at' => Carbon::parse((string) ($wp->post_date_gmt ?: $wp->post_date), 'UTC')->toDateTimeString(),
        'has_thumbnail' => $thumbnailId !== '',
    ];
}

$posts = Post::query()
    ->with(['author', 'category', 'tags', 'featuredImage', 'metas'])
    ->whereHas('metas', fn ($query) => $query->where('meta_key', 'wordpress_wxr_id'))
    ->get();

$mapped = [];
$issues = [];
$seoScores = [];
$missingFeatured = [];
$oldUploadReferences = [];
$missingContentMediaFiles = [];
$postsWithoutSourceImage = [];

foreach ($posts as $post) {
    $wordpressId = (string) ($post->metas->firstWhere('meta_key', 'wordpress_wxr_id')?->meta_value ?? '');
    $mapped[$wordpressId] = $post->id;
    $source = $expected[$wordpressId] ?? null;

    if (! $source) {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Imported post is not an expected published WXR article.'];
        continue;
    }

    if ($post->status !== 'published' || $post->visibility !== 'public') {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Post is not publicly published.'];
    }
    if ($post->published_at?->toDateTimeString() !== $source['published_at']) {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Publication date differs from WXR.'];
    }
    if ($post->author?->name !== 'ziad.mansor') {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Author is not ziad.mansor.'];
    }
    if (! $post->category || $post->tags->isEmpty()) {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Category or tags are missing.'];
    }
    if (trim((string) $post->content) === '' || trim((string) $post->excerpt) === '') {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Content or excerpt is empty.'];
    }
    if (trim((string) $post->seo_title) === '' || trim((string) $post->seo_description) === '' || trim((string) $post->focus_keyword) === '') {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Required SEO field is empty.'];
    }
    if (mb_strlen((string) $post->seo_title) > 65 || mb_strlen((string) $post->seo_description) > 160) {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'SEO title or description exceeds its limit.'];
    }
    if ($source['has_thumbnail'] && ! $post->featuredImage) {
        $missingFeatured[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'title' => $post->title];
    }
    if (! $source['has_thumbnail'] && ! $post->featuredImage) {
        $postsWithoutSourceImage[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'title' => $post->title];
    }
    if ($post->featuredImage && ! Storage::disk($post->featuredImage->disk)->exists($post->featuredImage->path)) {
        $issues[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'issue' => 'Featured image database record exists but file is missing.'];
    }
    if (preg_match_all('#https?://(?:www\.)?art-inpa\.com/wp-content/uploads/[^\s"\']+#i', (string) $post->content, $matches)) {
        $oldUploadReferences[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'urls' => array_values(array_unique($matches[0]))];
    }
    if (preg_match_all('#(?:https?://[^/]+)?/storage/(blog/imported/[^\s"\']+)#i', (string) $post->content, $matches)) {
        foreach (array_unique($matches[1]) as $path) {
            $path = html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! Storage::disk('public')->exists($path)) {
                $missingContentMediaFiles[] = ['wordpress_id' => $wordpressId, 'post_id' => $post->id, 'path' => $path];
            }
        }
    }

    $seoScores[] = (int) $post->seo_score;
}

$missingIds = array_values(array_diff(array_keys($expected), array_keys($mapped)));
sort($missingIds, SORT_NUMERIC);
sort($emptyTitleIds, SORT_NUMERIC);
$importedMedia = Media::query()->where('path', 'like', 'blog/imported/%')->get();
$missingMediaRecords = $importedMedia
    ->filter(fn (Media $media): bool => ! Storage::disk($media->disk)->exists($media->path))
    ->map(fn (Media $media): array => ['id' => $media->id, 'path' => $media->path])
    ->values()
    ->all();

$report = [
    'expected_published_with_title' => count($expected),
    'imported_from_wxr' => count($posts),
    'missing_wordpress_ids' => $missingIds,
    'intentionally_skipped_empty_title_ids' => $emptyTitleIds,
    'issues' => $issues,
    'source_thumbnails_missing_after_import' => $missingFeatured,
    'posts_without_any_source_image' => $postsWithoutSourceImage,
    'old_upload_references_remaining' => $oldUploadReferences,
    'media' => [
        'imported_records' => $importedMedia->count(),
        'missing_record_files' => $missingMediaRecords,
        'missing_content_files' => $missingContentMediaFiles,
    ],
    'seo' => [
        'minimum_score' => $seoScores === [] ? null : min($seoScores),
        'average_score' => $seoScores === [] ? null : round(array_sum($seoScores) / count($seoScores), 1),
        'scores_below_70' => count(array_filter($seoScores, fn (int $score): bool => $score < 70)),
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

exit($missingIds === [] && $issues === [] && $missingFeatured === [] && $oldUploadReferences === [] && $missingMediaRecords === [] && $missingContentMediaFiles === [] ? 0 : 1);
