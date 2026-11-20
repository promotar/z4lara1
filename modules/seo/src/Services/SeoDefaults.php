<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Services\SettingsRepository;
use Modules\Seo\Models\SeoSetting;
use Throwable;

/**
 * Resolves global SEO defaults with a safe fallback chain:
 * module setting -> platform setting -> application fallback.
 */
final class SeoDefaults
{
    public function __construct(
        private readonly SeoSiteResolver $sites,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forSite(?int $siteId = null): array
    {
        $siteId ??= $this->sites->id();
        $platform = $this->platformValues();

        $siteName = $this->value($siteId, 'general', 'site_name', '', (string) ($platform['general.site_title'] ?? config('app.name', 'Art INPA')));
        $description = $this->value($siteId, 'general', 'default_description', '', (string) ($platform['seo.seo_description'] ?? ''));
        $image = $this->value($siteId, 'general', 'default_og_image', '', (string) ($platform['seo.open_graph_image'] ?? ''));
        $suffix = $this->value($siteId, 'general', 'title_suffix', '', '');
        $titleTemplate = $this->value($siteId, 'general', 'title_template', '', '');
        $descriptionTemplate = $this->value($siteId, 'general', 'description_template', '', '{excerpt}');
        $robotsBehavior = (string) $this->value($siteId, 'general', 'robots_behavior', '', '');
        $canonicalBehavior = (string) $this->value($siteId, 'general', 'canonical_behavior', '', 'auto');

        if ($titleTemplate === '') {
            $titleTemplate = $suffix !== '' ? '{title}'.$suffix : '{title} | {site_name}';
        }

        if ($robotsBehavior === '') {
            $index = (bool) $this->value($siteId, 'robots', 'index', '', true);
            $follow = (bool) $this->value($siteId, 'robots', 'follow', '', true);
            $robotsBehavior = $index
                ? ($follow ? 'index_follow' : 'index_nofollow')
                : ($follow ? 'noindex_follow' : 'noindex_nofollow');
        }

        if (! in_array($robotsBehavior, ['index_follow', 'index_nofollow', 'noindex_follow', 'noindex_nofollow'], true)) {
            $robotsBehavior = 'index_follow';
        }

        if (! in_array($canonicalBehavior, ['auto', 'request', 'disabled'], true)) {
            $canonicalBehavior = 'auto';
        }

        $socialImage = (string) $this->value($siteId, 'social', 'image', '', '');
        $organizationUrl = (string) $this->value($siteId, 'organization', 'url', '', (string) ($platform['general.site_address_url'] ?? url('/')));

        return [
            'site_name' => $siteName,
            'default_description' => $description,
            'default_og_image' => $image,
            'title_template' => $titleTemplate,
            'title_suffix' => $suffix,
            'description_template' => $descriptionTemplate,
            'robots_behavior' => $robotsBehavior,
            'canonical_behavior' => $canonicalBehavior,
            'og_enabled' => (bool) $this->value($siteId, 'social', 'og_enabled', '', true),
            'social_title' => (string) $this->value($siteId, 'social', 'title', '', ''),
            'social_description' => (string) $this->value($siteId, 'social', 'description', '', ''),
            'social_image' => $socialImage !== '' ? $socialImage : $image,
            'organization_name' => (string) $this->value($siteId, 'organization', 'name', '', $siteName),
            'organization_logo' => (string) $this->value($siteId, 'organization', 'logo', '', (string) ($platform['general.site_logo'] ?? '')),
            'organization_url' => $organizationUrl,
            'organization_email' => (string) $this->value($siteId, 'organization', 'email', '', (string) ($platform['general.admin_email'] ?? '')),
            'organization_phone' => (string) $this->value($siteId, 'organization', 'phone', '', ''),
            'google_verification' => (string) $this->value($siteId, 'verification', 'google', '', ''),
            'bing_verification' => (string) $this->value($siteId, 'verification', 'bing', '', ''),
        ];
    }

    /**
     * @return array{index: bool, follow: bool}
     */
    public function robotsFlags(array $defaults): array
    {
        return match ((string) ($defaults['robots_behavior'] ?? 'index_follow')) {
            'noindex_follow' => ['index' => false, 'follow' => true],
            'index_nofollow' => ['index' => true, 'follow' => false],
            'noindex_nofollow' => ['index' => false, 'follow' => false],
            default => ['index' => true, 'follow' => true],
        };
    }

    private function value(int $siteId, string $group, string $key, string $locale, mixed $default): mixed
    {
        try {
            return SeoSetting::valueFor($siteId, 'seo', $group, $key, $locale, $default);
        } catch (Throwable $exception) {
            report($exception);

            return $default;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function platformValues(): array
    {
        try {
            return $this->settings->values();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }
}
