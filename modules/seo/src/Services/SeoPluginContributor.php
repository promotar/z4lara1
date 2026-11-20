<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Seo\Robots;
use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Throwable;

final class SeoPluginContributor implements SeoContributor
{
    private ?SeoDefaults $defaults;

    public function __construct(
        private readonly SeoSiteResolver $sites,
        ?SeoDefaults $defaults = null,
    ) {
        $this->defaults = $defaults;
    }

    public function module(): string
    {
        return 'seo';
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            $defaults = $this->defaults ?? app(SeoDefaults::class);
            $settings = $defaults->forSite($this->sites->id());
            $siteName = trim((string) ($settings['site_name'] ?? ''));

            if ($siteName !== '') {
                $seo->siteName = $siteName;
            }

            $this->applyTitle($seo, (string) ($settings['title_template'] ?? ''), $siteName);
            $this->applyDescription($seo, (string) ($settings['default_description'] ?? ''));
            $this->applyCanonical($seo, $request, (string) ($settings['canonical_behavior'] ?? 'auto'));
            $this->applyRobots($seo, $defaults, $settings);
            $this->applySocial($seo, $settings);

            $googleVerification = trim((string) ($settings['google_verification'] ?? ''));
            $bingVerification = trim((string) ($settings['bing_verification'] ?? ''));

            if ($googleVerification !== '') {
                $seo->meta['google-site-verification'] = $googleVerification;
            }

            if ($bingVerification !== '') {
                $seo->meta['msvalidate.01'] = $bingVerification;
            }

            $orgName = trim((string) ($settings['organization_name'] ?? '')) ?: $siteName ?: (string) config('app.name', 'Art INPA');
            $orgUrl = $this->absolute((string) ($settings['organization_url'] ?? '')) ?: url('/');
            $organization = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $orgName,
                'url' => $orgUrl,
                'logo' => $this->absolute((string) ($settings['organization_logo'] ?? '')),
                'email' => trim((string) ($settings['organization_email'] ?? '')) ?: null,
                'telephone' => trim((string) ($settings['organization_phone'] ?? '')) ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $seo->schema[] = $organization;
            $seo->schema[] = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $orgName,
                'url' => $orgUrl,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function applyTitle(SeoData $seo, string $template, string $siteName): void
    {
        $title = trim((string) ($seo->title ?? ''));

        if ($title === '') {
            $seo->title = $siteName !== '' ? $siteName : null;

            return;
        }

        if ($template === '') {
            return;
        }

        if ($siteName !== '' && str_contains($title, $siteName)) {
            return;
        }

        $title = trim(str_replace(
            ['{title}', '{site_name}'],
            [$title, $siteName],
            $template,
        ));

        if ($title !== '') {
            $seo->title = $title;
        }
    }

    private function applyDescription(SeoData $seo, string $defaultDescription): void
    {
        if (($seo->description === null || trim((string) $seo->description) === '') && $defaultDescription !== '') {
            $seo->description = $defaultDescription;
        }
    }

    private function applyCanonical(SeoData $seo, Request $request, string $behavior): void
    {
        if ($behavior === 'request') {
            $seo->canonical = $request->url();
        } elseif ($behavior === 'disabled') {
            $seo->canonical = null;
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applyRobots(SeoData $seo, SeoDefaults $defaults, array $settings): void
    {
        $flags = $defaults->robotsFlags($settings);

        if (! $flags['index'] || ! $flags['follow']) {
            $seo->robots = $seo->robots->merge(new Robots(
                index: $flags['index'],
                follow: $flags['follow'],
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applySocial(SeoData $seo, array $settings): void
    {
        if (! (bool) ($settings['og_enabled'] ?? true)) {
            $seo->ogEnabled = false;

            return;
        }

        $seo->ogEnabled = true;
        $seo->ogTitle = trim((string) ($settings['social_title'] ?? '')) ?: $seo->title;
        $seo->ogDescription = trim((string) ($settings['social_description'] ?? '')) ?: $seo->description;
        $seo->twitterTitle = $seo->ogTitle;
        $seo->twitterDescription = $seo->ogDescription;

        $image = $this->absolute((string) ($settings['social_image'] ?? ''));

        if ($image !== '' && ($seo->image === null || trim((string) $seo->image) === '')) {
            $seo->image = $image;
        }

        if ($seo->image) {
            $seo->twitterImage = $seo->image;
            $seo->twitterCard = $seo->twitterCard ?: 'summary_large_image';
        }
    }

    private function absolute(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
