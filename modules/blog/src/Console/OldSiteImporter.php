<?php

namespace Modules\Blog\Console;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Media;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostMeta;
use Modules\Blog\Models\Tag;
use Modules\Blog\Services\SeoScoreCalculator;

class OldSiteImporter
{
    private const SOURCE = 'https://art-inpa.com';

    private const USER_AGENT = 'ArtINPA-Laravel-Migration/1.0';

    private const MAX_IMAGE_BYTES = 12582912;

    private bool $dryRun = true;

    private bool $updateExisting = false;

    private string $sourceUrl = self::SOURCE;

    /** @var array<string, mixed> */
    private array $report = [];

    public function run(Command $command): int
    {
        $this->dryRun = (bool) $command->option('dry-run');
        $this->updateExisting = (bool) $command->option('update');
        $this->sourceUrl = rtrim((string) ($command->option('source') ?: self::SOURCE), '/');
        $all = (bool) $command->option('all');
        $batch = (int) ($command->option('batch') ?: 10);
        $batch = max(1, min($batch, 100));
        $limit = (int) ($command->option('batch') ?: $command->option('limit') ?: 3);
        $limit = max(1, min($limit, 100));

        $this->report = [
            'started_at' => now()->toIso8601String(),
            'source' => $this->sourceUrl,
            'mode' => $this->dryRun ? 'dry-run' : 'import',
            'scope' => $all ? 'all' : 'limited',
            'limit' => $all ? null : $limit,
            'batch' => $all ? $batch : null,
            'examined' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'images_downloaded' => 0,
            'images_reused' => 0,
            'internal_links_rewritten' => 0,
            'media_links_rewritten' => 0,
            'unresolved_media_posts_drafted' => 0,
            'errors' => [],
            'posts' => [],
        ];

        $posts = $all ? $this->fetchAllPosts($batch, $command) : $this->fetchPosts($limit);
        $this->report['examined'] = count($posts);

        foreach ($posts as $post) {
            $this->importPost($post, $command);
        }

        $this->rewriteImportedContentLinks($command);

        $this->report['finished_at'] = now()->toIso8601String();
        $reportPath = $this->writeReport();

        $command->info('Blog old-site import report: '.$reportPath);
        $command->line('Examined: '.$this->report['examined']);
        $command->line('Imported: '.$this->report['imported']);
        $command->line('Updated: '.$this->report['updated']);
        $command->line('Skipped: '.$this->report['skipped']);
        $command->line('Images downloaded: '.$this->report['images_downloaded']);
        $command->line('Images reused: '.$this->report['images_reused']);
        $command->line('Internal links rewritten: '.$this->report['internal_links_rewritten']);
        $command->line('Media links rewritten: '.$this->report['media_links_rewritten']);
        $command->line('Unresolved media posts drafted: '.$this->report['unresolved_media_posts_drafted']);

        if (! empty($this->report['errors'])) {
            $command->warn('Errors: '.count($this->report['errors']));
        }

        return empty($this->report['errors']) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPosts(int $limit): array
    {
        return $this->fetchPostPage($limit, 1)['posts'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPosts(int $batch, Command $command): array
    {
        $posts = [];
        $page = 1;
        $totalPages = null;

        do {
            $result = $this->fetchPostPage($batch, $page);
            $posts = array_merge($posts, $result['posts']);
            $totalPages = $totalPages ?: $result['total_pages'];
            $command->line('Fetched old-site page '.$page.' of '.($totalPages ?: '?').' ('.count($result['posts']).' posts).');
            $page++;

            if ($page <= (int) $totalPages) {
                usleep(750000);
            }
        } while ($totalPages !== null && $page <= $totalPages);

        return $posts;
    }

    /**
     * @return array{posts: array<int, array<string, mixed>>, total_pages: int|null}
     */
    private function fetchPostPage(int $limit, int $page): array
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
        ])
            ->timeout(25)
            ->retry(2, 500)
            ->get($this->sourceUrl.'/wp-json/wp/v2/posts', [
                'per_page' => $limit,
                'page' => $page,
                '_embed' => 1,
                'orderby' => 'date',
                'order' => 'desc',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('WordPress REST API returned HTTP '.$response->status());
        }

        return [
            'posts' => $response->json() ?: [],
            'total_pages' => $response->header('X-WP-TotalPages') ? (int) $response->header('X-WP-TotalPages') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $wpPost
     */
    private function importPost(array $wpPost, Command $command): void
    {
        $oldId = (string) ($wpPost['id'] ?? '');
        $oldUrl = (string) ($wpPost['link'] ?? '');
        $title = $this->plainText((string) data_get($wpPost, 'title.rendered', 'Untitled'));
        $existing = $this->findExistingPost($oldId, $oldUrl);

        $entry = [
            'old_id' => $oldId,
            'old_url' => $oldUrl,
            'title' => $title,
            'status' => 'pending',
            'new_url' => null,
            'images' => [
                'content_found' => 0,
                'downloaded' => 0,
                'reused' => 0,
                'failed' => 0,
            ],
            'categories' => [],
            'tags' => [],
            'seo' => [],
            'errors' => [],
        ];

        if ($title === '') {
            $entry['status'] = 'skipped-missing-title';
            $entry['errors'][] = 'Old WordPress post has no title, so it was not imported or published.';
            $this->report['skipped']++;
            $this->report['posts'][] = $entry;
            $command->warn('Skipped missing title: old post '.$oldId);

            return;
        }

        if ($existing && ! $this->updateExisting) {
            $this->report['skipped']++;
            $entry['status'] = 'skipped-existing';
            $entry['new_url'] = url('/blog/'.$existing->slug);
            $this->report['posts'][] = $entry;
            $command->line('Skipped existing: '.$title);

            return;
        }

        if ($this->dryRun) {
            $content = (string) data_get($wpPost, 'content.rendered', '');
            $entry['status'] = $existing ? 'would-update' : 'would-import';
            $entry['images']['content_found'] = $this->countContentImages($content);
            $entry['categories'] = array_map(fn (array $term): string => $term['name'], $this->terms($wpPost, 'category'));
            $entry['tags'] = array_map(fn (array $term): string => $term['name'], $this->terms($wpPost, 'post_tag'));
            $entry['seo'] = $this->seoPayload($wpPost, $title, $content, $this->slugForPost($wpPost, $title, $existing?->id), false);
            $this->report['posts'][] = $entry;
            $command->line('Dry run: '.$title);

            return;
        }

        try {
            $categoryIds = $this->syncCategories($wpPost);
            $tagIds = $this->syncTags($wpPost);
            $rawContent = (string) data_get($wpPost, 'content.rendered', '');
            $imageStats = ['content_found' => 0, 'downloaded' => 0, 'reused' => 0, 'failed' => 0];
            $imageErrors = [];
            $content = $this->rewriteContentImages($rawContent, (int) $wpPost['id'], $imageStats, $imageErrors);
            $content = $this->sanitizeHtml($content);
            $slug = $this->uniqueSlug($this->slugForPost($wpPost, $title, $existing?->id), $existing?->id);
            $featured = $this->featuredMedia($wpPost, (int) $wpPost['id'], $imageErrors);
            $excerpt = $this->excerpt($wpPost, $content);
            $seo = $this->seoPayload($wpPost, $title, $content, $slug, true, $excerpt);
            $oldStatus = (string) ($wpPost['status'] ?? 'draft');
            $status = $oldStatus === 'publish' && empty($imageErrors) ? 'published' : 'draft';
            $publishedAt = $this->publishedAt($wpPost);
            $authorId = User::query()->orderBy('id')->value('id');

            $payload = [
                'category_id' => $categoryIds[0] ?? null,
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'status' => $status,
                'visibility' => 'public',
                'featured_image_id' => $featured['media_id'] ?? null,
                'featured_image' => $featured['url'] ?? null,
                'featured_image_alt' => $featured['alt_text'] ?? null,
                'template' => 'default',
                'layout' => 'default',
                'layout_template' => 'default',
                'seo_title' => $seo['seo_title'],
                'seo_description' => $seo['seo_description'],
                'meta_title' => $seo['seo_title'],
                'meta_description' => $seo['seo_description'],
                'focus_keyword' => $seo['focus_keyword'],
                'seo_focus_keyword' => $seo['focus_keyword'],
                'canonical_url' => $seo['canonical_url'],
                'robots_index' => true,
                'robots_follow' => true,
                'schema_type' => 'Article',
                'seo_schema_type' => 'Article',
                'seo_score' => app(SeoScoreCalculator::class)->calculate([
                    ...$seo,
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'category_id' => $categoryIds[0] ?? null,
                    'featured_image' => $featured['url'] ?? null,
                ], $tagIds === [] ? '' : implode(',', $tagIds)),
                'published_at' => $publishedAt,
                'scheduled_at' => null,
                'author_id' => $authorId,
                'created_by' => $authorId,
                'updated_by' => $authorId,
            ];

            $post = $existing ?: new Post;
            $post->fill($payload);
            $post->save();
            $post->categories()->sync($categoryIds);
            $post->tags()->sync($tagIds);

            $this->meta($post, 'old_id', $oldId);
            $this->meta($post, 'old_url', $oldUrl);
            $this->meta($post, 'old_author_name', $this->oldAuthorName($wpPost));
            $this->meta($post, 'old_categories', json_encode($this->terms($wpPost, 'category'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->meta($post, 'old_tags', json_encode($this->terms($wpPost, 'post_tag'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->meta($post, 'old_payload_hash', sha1(json_encode($wpPost)));

            $newUrl = url('/blog/'.$post->slug);
            $this->updateMapping($oldUrl, $newUrl, (int) $post->id);

            $entry['status'] = $existing ? 'updated' : 'imported';
            $entry['new_url'] = $newUrl;
            $entry['images'] = $imageStats;
            $entry['categories'] = array_map(fn (array $term): string => $term['name'], $this->terms($wpPost, 'category'));
            $entry['tags'] = array_map(fn (array $term): string => $term['name'], $this->terms($wpPost, 'post_tag'));
            $entry['seo'] = $seo;
            $entry['errors'] = $imageErrors;

            $this->report[$existing ? 'updated' : 'imported']++;
            $this->report['images_downloaded'] += $imageStats['downloaded'];
            $this->report['images_reused'] += $imageStats['reused'];

            if (! empty($imageErrors)) {
                $this->report['errors'][] = [
                    'old_id' => $oldId,
                    'old_url' => $oldUrl,
                    'message' => 'Imported as draft because one or more images failed.',
                    'details' => $imageErrors,
                ];
            }

            $this->report['posts'][] = $entry;
            $command->info(($existing ? 'Updated: ' : 'Imported: ').$title);
            usleep(250000);
        } catch (\Throwable $exception) {
            $this->report['errors'][] = [
                'old_id' => $oldId,
                'old_url' => $oldUrl,
                'message' => $exception->getMessage(),
            ];
            $entry['status'] = 'failed';
            $entry['errors'][] = $exception->getMessage();
            $this->report['posts'][] = $entry;
            $command->error('Failed: '.$title.' - '.$exception->getMessage());
        }
    }

    private function findExistingPost(string $oldId, string $oldUrl): ?Post
    {
        return Post::withTrashed()
            ->whereHas('metas', function ($query) use ($oldId, $oldUrl): void {
                $query->where(function ($meta) use ($oldId, $oldUrl): void {
                    $meta
                        ->where(fn ($q) => $q->where('meta_key', 'old_id')->where('meta_value', $oldId))
                        ->orWhere(fn ($q) => $q->where('meta_key', 'old_url')->where('meta_value', $oldUrl));
                });
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $wpPost
     * @return array<int, int>
     */
    private function syncCategories(array $wpPost): array
    {
        return collect($this->terms($wpPost, 'category'))
            ->map(function (array $term): int {
                return Category::query()->firstOrCreate([
                    'slug' => $this->termSlug($term),
                ], [
                    'name' => $term['name'],
                    'description' => '',
                ])->id;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $wpPost
     * @return array<int, int>
     */
    private function syncTags(array $wpPost): array
    {
        return collect($this->terms($wpPost, 'post_tag'))
            ->map(function (array $term): int {
                return Tag::query()->firstOrCreate([
                    'slug' => $this->termSlug($term),
                ], [
                    'name' => $term['name'],
                ])->id;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $wpPost
     * @return array<int, array{name: string, slug: string, old_id: int|string}>
     */
    private function terms(array $wpPost, string $taxonomy): array
    {
        $terms = [];

        foreach ((array) data_get($wpPost, '_embedded.wp:term', []) as $group) {
            foreach ((array) $group as $term) {
                if (($term['taxonomy'] ?? null) !== $taxonomy) {
                    continue;
                }

                $terms[] = [
                    'name' => $this->plainText((string) ($term['name'] ?? '')),
                    'slug' => (string) ($term['slug'] ?? ''),
                    'old_id' => $term['id'] ?? '',
                ];
            }
        }

        return $terms;
    }

    /**
     * @param  array<string, mixed>  $term
     */
    private function termSlug(array $term): string
    {
        $slug = Str::slug(rawurldecode((string) ($term['slug'] ?? '')));

        return $slug ?: Str::slug((string) ($term['name'] ?? 'term')) ?: 'term-'.($term['old_id'] ?? md5(json_encode($term)));
    }

    /**
     * @param  array<string, mixed>  $wpPost
     */
    private function slugForPost(array $wpPost, string $title, ?int $existingId): string
    {
        if ($existingId) {
            $existing = Post::withTrashed()->find($existingId);
            if ($existing?->slug) {
                return $existing->slug;
            }
        }

        $slug = Str::slug(rawurldecode((string) ($wpPost['slug'] ?? '')));

        return $slug ?: Str::slug($title) ?: 'old-post-'.($wpPost['id'] ?? Str::random(8));
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;

        while (Post::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function countContentImages(string $html): int
    {
        return preg_match_all('/<img\b/i', $html);
    }

    /**
     * @param  array{content_found: int, downloaded: int, reused: int, failed: int}  $stats
     * @param  array<int, string>  $errors
     */
    private function rewriteContentImages(string $html, int $oldPostId, array &$stats, array &$errors): string
    {
        $document = $this->htmlDocument($html);
        $xpath = new DOMXPath($document);
        $images = iterator_to_array($xpath->query('//img') ?: []);
        $stats['content_found'] = count($images);

        foreach ($images as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }

            $oldSrc = $this->imageSource($image);

            if ($oldSrc === null) {
                continue;
            }

            $absolute = $this->absoluteUrl($oldSrc);
            $media = $this->downloadImage($absolute, $oldPostId, $image->getAttribute('alt') ?: null, $image->getAttribute('title') ?: null, null, $errors);

            if ($media) {
                $image->setAttribute('src', $media['url']);
                $image->removeAttribute('srcset');
                $image->removeAttribute('sizes');
                $image->removeAttribute('data-src');
                $this->rewriteWrappingImageLink($image, $absolute, $media['url']);
                $stats[$media['reused'] ? 'reused' : 'downloaded']++;
            } else {
                $image->setAttribute('src', '');
                $stats['failed']++;
            }

            usleep(150000);
        }

        return $this->documentInnerHtml($document);
    }

    private function rewriteWrappingImageLink(DOMElement $image, string $oldImageUrl, string $newImageUrl): void
    {
        $parent = $image->parentNode;

        if (! $parent instanceof DOMElement || strtolower($parent->tagName) !== 'a') {
            return;
        }

        $href = trim($parent->getAttribute('href'));

        if ($href === '' || $this->absoluteUrl($href) !== $oldImageUrl) {
            return;
        }

        $parent->setAttribute('href', $newImageUrl);
    }

    private function imageSource(DOMElement $image): ?string
    {
        foreach (['src', 'data-src', 'data-lazy-src'] as $attribute) {
            $value = trim($image->getAttribute($attribute));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $errors
     * @return array{media_id: int, url: string, alt_text: string|null, reused: bool}|null
     */
    private function featuredMedia(array $wpPost, int $oldPostId, array &$errors): ?array
    {
        $featured = (array) data_get($wpPost, '_embedded.wp:featuredmedia.0', []);
        $source = (string) ($featured['source_url'] ?? '');

        if ($source === '') {
            return null;
        }

        return $this->downloadImage(
            $source,
            $oldPostId,
            (string) ($featured['alt_text'] ?? ''),
            $this->plainText((string) data_get($featured, 'title.rendered', '')),
            $this->plainText((string) data_get($featured, 'caption.rendered', '')),
            $errors,
        );
    }

    /**
     * @param  array<int, string>  $errors
     * @return array{media_id: int, url: string, alt_text: string|null, reused: bool}|null
     */
    private function downloadImage(string $url, int $oldPostId, ?string $alt, ?string $title, ?string $caption, array &$errors): ?array
    {
        if (! str_starts_with($url, 'https://art-inpa.com/') && ! str_starts_with($url, 'http://art-inpa.com/')) {
            $errors[] = 'Skipped non-source image URL: '.$url;

            return null;
        }

        $hash = sha1($url);
        $existing = Media::query()->where('path', 'like', 'blog/imported/%/'.$hash.'.%')->first();

        if ($existing && Storage::disk('public')->exists($existing->path)) {
            return [
                'media_id' => $existing->id,
                'url' => $existing->url,
                'alt_text' => $existing->alt_text,
                'reused' => true,
            ];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif,image/x-icon,*/*;q=0.5',
            ])->timeout(30)->retry(2, 700)->get($url);
        } catch (\Throwable $exception) {
            $errors[] = 'Image download failed: '.$url.' - '.$exception->getMessage();

            return null;
        }

        if (! $response->successful()) {
            $errors[] = 'Image HTTP '.$response->status().': '.$url;

            return null;
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_IMAGE_BYTES) {
            $errors[] = 'Image too large: '.$url;

            return null;
        }

        $mime = strtolower((string) ($response->header('Content-Type') ?: ''));
        $mime = trim(explode(';', $mime)[0]);
        $extension = $this->extensionForMime($mime, $url);

        if ($extension === null) {
            $errors[] = 'Unsupported image mime '.$mime.': '.$url;

            return null;
        }

        $path = 'blog/imported/'.$oldPostId.'/'.$hash.'.'.$extension;
        Storage::disk('public')->put($path, $body);
        $urlLocal = Storage::url($path);
        [$width, $height] = $this->imageDimensions($body);
        $mediaTitle = $this->limitText($title ?: basename(parse_url($url, PHP_URL_PATH) ?: $path), 190);

        $media = Media::query()->updateOrCreate([
            'path' => $path,
        ], [
            'disk' => 'public',
            'url' => $urlLocal,
            'mime_type' => $mime,
            'size' => strlen($body),
            'width' => $width,
            'height' => $height,
            'alt_text' => $alt,
            'title' => $mediaTitle,
            'caption' => $caption,
            'description' => '',
            'uploaded_by' => User::query()->orderBy('id')->value('id'),
        ]);

        return [
            'media_id' => $media->id,
            'url' => $urlLocal,
            'alt_text' => $media->alt_text,
            'reused' => false,
        ];
    }

    private function extensionForMime(string $mime, string $url): ?string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => $this->safeExtensionFromUrl($url),
        };
    }

    private function safeExtensionFromUrl(string $url): ?string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico'], true)
            ? ($extension === 'jpeg' ? 'jpg' : $extension)
            : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensions(string $body): array
    {
        $size = @getimagesizefromstring($body);

        return is_array($size) ? [$size[0] ?? null, $size[1] ?? null] : [null, null];
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->sourceUrl.'/'.ltrim($url, '/');
    }

    private function sanitizeHtml(string $html): string
    {
        $document = $this->htmlDocument($html);
        $xpath = new DOMXPath($document);

        foreach (['//script', '//style', '//noscript', '//iframe', '//object', '//embed', '//form', '//input', '//button'] as $query) {
            foreach (iterator_to_array($xpath->query($query) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $remove = [];
            foreach ($node->attributes ?: [] as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                if (str_starts_with($name, 'on') || str_starts_with($name, 'data-') || in_array($name, ['style', 'srcset', 'sizes'], true)) {
                    $remove[] = $attribute->name;

                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $value)) {
                    $remove[] = $attribute->name;
                }
            }

            foreach ($remove as $attribute) {
                $node->removeAttribute($attribute);
            }
        }

        return $this->documentInnerHtml($document);
    }

    private function htmlDocument(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="import-root">'.$html.'</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();

        return $document;
    }

    private function documentInnerHtml(DOMDocument $document): string
    {
        $root = $document->getElementById('import-root');
        if (! $root) {
            return '';
        }

        $html = '';
        foreach ($root->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }

        return trim($html);
    }

    private function excerpt(array $wpPost, string $content): string
    {
        $excerpt = $this->plainText((string) data_get($wpPost, 'excerpt.rendered', ''));

        return $excerpt !== '' ? $excerpt : $this->limitText($this->plainText($content), 180);
    }

    /**
     * @return array{seo_title: string, seo_description: string, focus_keyword: string, canonical_url: string}
     */
    private function seoPayload(array $wpPost, string $title, string $content, string $slug, bool $newCanonical, ?string $excerpt = null): array
    {
        $yoastTitle = $this->plainText((string) data_get($wpPost, 'yoast_head_json.title', ''));
        $yoastDescription = $this->plainText((string) data_get($wpPost, 'yoast_head_json.description', ''));
        $description = $yoastDescription ?: ($excerpt ?: $this->limitText($this->plainText($content), 155));

        return [
            'seo_title' => $this->limitText($yoastTitle ?: $title, 65),
            'seo_description' => $this->limitText($description, 160),
            'focus_keyword' => $this->focusKeyword($title),
            'canonical_url' => $newCanonical ? url('/blog/'.$slug) : (string) data_get($wpPost, 'yoast_head_json.canonical', ''),
        ];
    }

    private function focusKeyword(string $title): string
    {
        $words = preg_split('/\s+/u', trim($title)) ?: [];

        return trim(implode(' ', array_slice($words, 0, 4)));
    }

    private function publishedAt(array $wpPost): ?Carbon
    {
        $date = (string) ($wpPost['date_gmt'] ?? $wpPost['date'] ?? '');

        return $date !== '' ? Carbon::parse($date, 'UTC') : null;
    }

    private function oldAuthorName(array $wpPost): string
    {
        return $this->plainText((string) data_get($wpPost, '_embedded.author.0.name', ''));
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
    }

    private function limitText(string $value, int $limit): string
    {
        $value = trim($value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1).'…' : $value;
    }

    private function meta(Post $post, string $key, ?string $value): void
    {
        PostMeta::query()->updateOrCreate([
            'post_id' => $post->id,
            'meta_key' => $key,
        ], [
            'meta_value' => $value,
        ]);
    }

    private function rewriteImportedContentLinks(Command $command): void
    {
        if ($this->dryRun) {
            return;
        }

        $mapping = $this->readMapping();
        $posts = Post::query()
            ->where('content', 'like', '%art-inpa.com%')
            ->get(['id', 'title', 'status', 'content']);

        $changedPosts = 0;

        foreach ($posts as $post) {
            $content = (string) $post->content;
            $updated = $content;

            foreach ($mapping as $oldUrl => $target) {
                $newUrl = is_array($target) ? (string) ($target['new_url'] ?? '') : '';

                if ($oldUrl !== '' && $newUrl !== '' && str_contains($updated, $oldUrl)) {
                    $updated = str_replace($oldUrl, $newUrl, $updated);
                    $this->report['internal_links_rewritten']++;
                }
            }

            if (preg_match_all('/href=(["\'])([^"\']*art-inpa\.com[^"\']*)\1/i', $updated, $matches)) {
                foreach (array_unique($matches[2]) as $oldMediaUrl) {
                    $media = $this->mediaForSourceUrl($oldMediaUrl);
                    $newMediaUrl = $media?->url;

                    if (! $newMediaUrl && str_contains($oldMediaUrl, '/wp-content/uploads/')) {
                        $downloadErrors = [];
                        $downloaded = $this->downloadImage($this->absoluteUrl($oldMediaUrl), 0, null, null, null, $downloadErrors);

                        if ($downloaded) {
                            $newMediaUrl = $downloaded['url'];
                            $this->report[$downloaded['reused'] ? 'images_reused' : 'images_downloaded']++;
                        } else {
                            foreach ($downloadErrors as $error) {
                                $this->report['errors'][] = [
                                    'old_url' => $oldMediaUrl,
                                    'message' => $error,
                                ];
                            }
                        }
                    }

                    if ($newMediaUrl) {
                        $updated = str_replace($oldMediaUrl, $newMediaUrl, $updated);
                        $this->report['media_links_rewritten']++;
                    }
                }
            }

            $attributes = [];

            if ($updated !== $content) {
                $attributes['content'] = $updated;
                $changedPosts++;
            }

            if (preg_match('/href=(["\'])([^"\']*art-inpa\.com\/wp-content\/uploads[^"\']*)\1/i', $updated, $unresolvedMedia)) {
                if ($post->status === 'published') {
                    $attributes['status'] = 'draft';
                    $this->report['unresolved_media_posts_drafted']++;
                }

                $this->report['errors'][] = [
                    'post_id' => $post->id,
                    'title' => $post->title,
                    'old_url' => $unresolvedMedia[2],
                    'message' => 'Post still references an old uploaded media file, so it was kept out of public publishing.',
                ];
            }

            if ($attributes !== []) {
                $post->forceFill($attributes)->saveQuietly();
            }
        }

        if ($changedPosts > 0) {
            $command->info('Rewritten old-site content links in '.$changedPosts.' posts.');
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readMapping(): array
    {
        $path = storage_path('app/blog-import/old-url-map.json');

        if (! is_file($path)) {
            return [];
        }

        $mapping = json_decode((string) file_get_contents($path), true);

        return is_array($mapping) ? $mapping : [];
    }

    private function mediaForSourceUrl(string $url): ?Media
    {
        $absolute = $this->absoluteUrl($url);

        if (! str_starts_with($absolute, 'https://art-inpa.com/') && ! str_starts_with($absolute, 'http://art-inpa.com/')) {
            return null;
        }

        $hash = sha1($absolute);

        return Media::query()
            ->where('path', 'like', 'blog/imported/%/'.$hash.'.%')
            ->first();
    }

    private function updateMapping(string $oldUrl, string $newUrl, int $postId): void
    {
        $path = storage_path('app/blog-import/old-url-map.json');
        File::ensureDirectoryExists(dirname($path));
        $mapping = $this->readMapping();
        $mapping[$oldUrl] = [
            'new_url' => $newUrl,
            'post_id' => $postId,
            'updated_at' => now()->toIso8601String(),
        ];
        file_put_contents($path, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function writeReport(): string
    {
        $directory = storage_path('app/blog-import/reports');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/import-'.now()->format('Ymd-His').($this->dryRun ? '-dry-run' : '').'.json';
        file_put_contents($path, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
