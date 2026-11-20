<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Throwable;

/**
 * Applies content-level overrides after module and global defaults have run.
 */
final class ContentSeoContributor implements SeoContributor
{
    private const EXTRA_KEYS = [
        'blog_post_id' => 'blog_post',
        'lms_course_id' => 'lms_course',
        'store_product_id' => 'store_product',
        'exhibitions_artwork_id' => 'exhibition_artwork',
        'platform_page_id' => 'page',
    ];

    public function __construct(
        private readonly ContentSeoService $contentSeo,
        private readonly SeoModuleSettings $moduleSettings,
        private readonly SeoKeywordExtractor $keywords,
        private readonly SeoDefaults $defaults,
    ) {
    }

    public function module(): string
    {
        return 'seo-content';
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            $type = null;
            $id = null;

            foreach (self::EXTRA_KEYS as $extraKey => $contentType) {
                if (isset($seo->extra[$extraKey]) && (int) $seo->extra[$extraKey] > 0) {
                    $type = $contentType;
                    $id = (int) $seo->extra[$extraKey];

                    break;
                }
            }

            if ($type === null || $id === null) {
                return;
            }

            $locale = app()->getLocale();
            $module = $this->moduleSettings->moduleForContentType($type);
            $flags = $module !== null
                ? $this->moduleSettings->forModule($module, $locale)
                : $this->moduleSettings->defaults();
            $override = $this->contentSeo->override($type, $id, $locale);

            // Apply legacy content overrides first, then render the module
            // templates around the effective values.
            if ($override['title'] !== '') {
                $seo->title = $override['title'];
            }

            if ($override['description'] !== '') {
                $seo->description = $override['description'];
            }

            if ($override['canonical'] !== '') {
                $seo->canonical = str_starts_with($override['canonical'], 'http://') || str_starts_with($override['canonical'], 'https://')
                    ? $override['canonical']
                    : url($override['canonical']);
            }

            if ($override['robots'] === 'noindex') {
                $seo->robots->index = false;
            } elseif ($override['robots'] === 'index') {
                $seo->robots->index = true;
            }

            $baseTitle = trim((string) $seo->title);
            $baseDescription = trim((string) $seo->description);
            $baseCanonical = trim((string) $seo->canonical);
            $baseRobots = $seo->robots->toMeta();
            $baseImage = trim((string) $seo->image);
            $baseImageAlt = trim((string) ($seo->imageAlt ?? ''));
            $focusKeywords = [];

            if ($flags['focus_keyword'] !== '') {
                $keywordText = trim((string) ($seo->extra['content_text'] ?? ''));

                if ($keywordText === '') {
                    $keywordText = $baseTitle.' '.$baseDescription;
                    $defaults = $this->defaults->forSite();

                    foreach ([
                        (string) ($seo->siteName ?? ''),
                        (string) ($defaults['site_name'] ?? ''),
                        (string) ($defaults['default_description'] ?? ''),
                    ] as $remove) {
                        if (trim($remove) !== '') {
                            $keywordText = str_ireplace($remove, ' ', $keywordText);
                        }
                    }
                }

                $focusKeywords = $this->keywords->extract($keywordText, 3);
            }

            $tokens = [
                '{seo_title}' => $baseTitle,
                '{meta_description}' => $baseDescription,
                '{content_title}' => trim((string) ($seo->extra['content_title'] ?? $baseTitle)),
                '{content_description}' => trim((string) ($seo->extra['content_description'] ?? $baseDescription)),
                '{site_name}' => (string) ($seo->siteName ?? ''),
                '{focus_keyword}' => implode(', ', $focusKeywords),
                '{robots}' => $baseRobots,
                '{canonical_url}' => $baseCanonical,
                '{seo_image}' => $baseImage,
                '{seo_image_alt}' => $baseImageAlt,
                '{google_preview}' => $baseTitle,
                '{social_preview}' => $baseTitle,
            ];

            $render = static fn (string $template, array $values = []): string => trim(strtr($template, array_merge($tokens, $values)));

            if ($flags['seo_title'] === '') {
                $seo->title = null;
                $seo->ogTitle = null;
                $seo->twitterTitle = null;
                $seo->extra['disable_title'] = true;
            } else {
                $renderedTitle = $render($flags['seo_title']);
                $seo->title = $renderedTitle !== '' ? $renderedTitle : null;
                if ($renderedTitle === '') {
                    $seo->extra['disable_title'] = true;
                }
            }

            if ($flags['meta_description'] === '') {
                $seo->description = null;
                $seo->ogDescription = null;
                $seo->twitterDescription = null;
                $seo->extra['disable_description'] = true;
            } else {
                $renderedDescription = $render($flags['meta_description']);
                $seo->description = $renderedDescription !== '' ? $renderedDescription : null;
                if ($renderedDescription === '') {
                    $seo->extra['disable_description'] = true;
                }
            }

            $finalTitle = trim((string) $seo->title);
            $finalDescription = trim((string) $seo->description);

            if ($flags['canonical'] === '') {
                $seo->canonical = null;
                $seo->extra['disable_canonical'] = true;
            } else {
                $renderedCanonical = $render($flags['canonical']);
                $seo->canonical = $renderedCanonical !== '' ? $renderedCanonical : null;
                if ($renderedCanonical === '') {
                    $seo->extra['disable_canonical'] = true;
                }
            }

            if ($flags['robots'] === '') {
                $seo->extra['disable_robots'] = true;
            } else {
                $renderedRobots = $render($flags['robots']);
                if ($renderedRobots === '') {
                    $seo->extra['disable_robots'] = true;
                } elseif ($renderedRobots !== $baseRobots) {
                    $seo->extra['robots_meta_override'] = $renderedRobots;
                }
            }

            unset($seo->meta['keywords']);

            if ($flags['focus_keyword'] === '') {
                $seo->extra['disable_keywords'] = true;
            } else {
                $renderedKeywords = $render($flags['focus_keyword']);
                if ($renderedKeywords !== '') {
                    $seo->meta['keywords'] = $renderedKeywords;
                    $seo->extra['focus_keywords'] = $focusKeywords;
                } else {
                    $seo->extra['disable_keywords'] = true;
                }
            }

            if ($flags['social_preview'] === '') {
                $seo->ogEnabled = false;
            } else {
                $renderedSocialTitle = $render($flags['social_preview'], [
                    '{seo_title}' => $finalTitle,
                    '{meta_description}' => $finalDescription,
                ]);
                $seo->ogTitle = $renderedSocialTitle !== '' ? $renderedSocialTitle : null;
                $seo->ogDescription = $finalDescription !== '' ? $finalDescription : null;
                $seo->twitterTitle = $seo->ogTitle;
                $seo->twitterDescription = $seo->ogDescription;
            }

            $renderedImageTitle = $flags['image_seo_title'] !== ''
                ? $render($flags['image_seo_title'], ['{seo_title}' => $baseTitle, '{meta_description}' => $baseDescription])
                : '';
            $renderedImageDescription = $flags['image_seo_description'] !== ''
                ? $render($flags['image_seo_description'], ['{seo_title}' => $baseTitle, '{meta_description}' => $baseDescription])
                : '';

            $seo->imageAlt = $renderedImageTitle !== '' ? $renderedImageTitle : null;
            $seo->extra['image_seo_title'] = $renderedImageTitle;
            $seo->extra['image_seo_description'] = $renderedImageDescription;

            if ($flags['google_preview'] === '') {
                $seo->extra['disable_google_preview'] = true;
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
