<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Modules\Seo\Models\SeoLifecycleLog;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Models\SeoModuleState;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Models\SeoSitemapChunk;
use Modules\Seo\Models\SeoSitemapRun;
use Throwable;

final class SeoModuleSyncManager
{
    public function __construct(private readonly SeoSiteResolver $sites)
    {
    }

    public function activate(string $slug): void
    {
        $siteId = $this->sites->id();
        $plugin = Plugin::query()->where('slug', $slug)->first();
        $state = SeoModuleState::query()->firstOrNew([
            'site_id' => $siteId,
            'module_slug' => $slug,
        ]);
        $before = $state->exists ? $state->toArray() : null;

        $state->fill([
            'version' => $plugin?->version,
            'status' => SeoModuleState::STATUS_ACTIVE,
            'activated_at' => now(),
            'deactivated_at' => null,
            'archived_at' => null,
            'purge_after' => null,
            'last_synced_at' => now(),
        ])->save();

        $this->seedDefaults($siteId, $slug);
        $this->log($siteId, $slug, 'activate', $before, $state->toArray());
        $this->clearCaches($siteId);
    }

    public function deactivate(string $slug): void
    {
        $siteId = $this->sites->id();
        $state = SeoModuleState::query()
            ->where('site_id', $siteId)
            ->where('module_slug', $slug)
            ->first();

        if ($state === null) {
            return;
        }

        $before = $state->toArray();
        $state->fill([
            'status' => SeoModuleState::STATUS_INACTIVE,
            'deactivated_at' => now(),
            'last_synced_at' => now(),
        ])->save();

        // Deactivation is data-preserving by design. Only purge removes data.
        $this->log($siteId, $slug, 'deactivate', $before, $state->toArray());
        $this->clearCaches($siteId);
    }

    public function archive(string $slug): void
    {
        $siteId = $this->sites->id();
        $state = SeoModuleState::query()->firstOrNew([
            'site_id' => $siteId,
            'module_slug' => $slug,
        ]);
        $before = $state->exists ? $state->toArray() : null;

        $state->fill([
            'status' => SeoModuleState::STATUS_ARCHIVED,
            'archived_at' => now(),
            'purge_after' => now()->addDays((int) config('seo.retention.uninstall_days', 90)),
            'last_synced_at' => now(),
        ])->save();

        $this->log($siteId, $slug, 'archive', $before, $state->toArray());
        $this->clearCaches($siteId);
    }

    public function purge(string $slug): void
    {
        $siteId = $this->sites->id();
        $state = SeoModuleState::query()
            ->where('site_id', $siteId)
            ->where('module_slug', $slug)
            ->first();
        $before = $state?->toArray();

        SeoSetting::query()->where('site_id', $siteId)->where('module_slug', $slug)->delete();
        SeoMeta::query()->where('site_id', $siteId)->where('module_slug', $slug)->delete();
        SeoRedirect::query()->where('site_id', $siteId)->where('module_slug', $slug)->delete();

        SeoModuleState::query()->updateOrCreate(
            ['site_id' => $siteId, 'module_slug' => $slug],
            [
                'status' => SeoModuleState::STATUS_PURGED,
                'purge_after' => null,
                'last_synced_at' => now(),
            ],
        );

        $this->log($siteId, $slug, 'purge', $before, ['status' => SeoModuleState::STATUS_PURGED]);
        $this->clearCaches($siteId);
    }

    /**
     * @return array{orphaned: list<string>, archived: list<string>, purged: list<string>}
     */
    public function reconcile(bool $purgeExpired = false): array
    {
        $siteId = $this->sites->id();
        $plugins = Plugin::query()->get(['slug', 'status']);
        $installedSlugs = $plugins
            ->pluck('slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->all();
        $activeSlugs = $plugins
            ->where('status', Plugin::STATUS_ACTIVE)
            ->pluck('slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->all();

        $result = ['orphaned' => [], 'archived' => [], 'purged' => []];
        $states = SeoModuleState::query()
            ->where('site_id', $siteId)
            ->get()
            ->keyBy('module_slug');

        // Ensure every active module has an active SEO state. This is what
        // seeds module defaults when the SEO plugin is installed after other
        // modules were already active.
        foreach ($activeSlugs as $slug) {
            if ($slug === 'seo') {
                continue;
            }

            $state = $states->get($slug);

            if ($state === null || $state->status !== SeoModuleState::STATUS_ACTIVE) {
                $this->activate($slug);
            }
        }

        foreach ($states as $state) {
            $slug = (string) $state->module_slug;

            if ($slug === 'seo' || in_array($slug, $activeSlugs, true)) {
                continue;
            }

            if (in_array($slug, $installedSlugs, true)) {
                if ($state->status !== SeoModuleState::STATUS_INACTIVE) {
                    // Installed but intentionally deactivated: keep data and
                    // mark it inactive rather than orphaned.
                    $this->deactivate($slug);
                }

                continue;
            }

            if (in_array($state->status, [SeoModuleState::STATUS_ACTIVE, SeoModuleState::STATUS_INACTIVE], true)) {
                $state->fill([
                    'status' => SeoModuleState::STATUS_ORPHANED,
                    'last_synced_at' => now(),
                ])->save();
                $result['orphaned'][] = $slug;

                continue;
            }

            if ($state->status === SeoModuleState::STATUS_ORPHANED
                && $state->updated_at !== null
                && $state->updated_at->lt(now()->subDays((int) config('seo.retention.orphaned_days', 30)))
            ) {
                $this->archive($slug);
                $result['archived'][] = $slug;

                continue;
            }

            if ($state->status === SeoModuleState::STATUS_ARCHIVED
                && $state->purge_after !== null
                && $state->purge_after->isPast()
            ) {
                if ($purgeExpired) {
                    $this->purge($slug);
                }

                $result['purged'][] = $slug;
            }
        }

        return $result;
    }

    private function seedDefaults(int $siteId, string $slug): void
    {
        $manifest = $this->manifest($slug);
        $defaults = data_get($manifest, 'seo.settings', []);

        if (! is_array($defaults)) {
            return;
        }

        foreach ($defaults as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            [$group, $setting] = array_pad(explode('.', $key, 2), 2, 'default');
            $type = is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string');

            SeoSetting::query()->firstOrCreate(
                [
                    'site_id' => $siteId,
                    'module_slug' => $slug,
                    'group_key' => $group,
                    'setting_key' => $setting,
                    'locale' => '',
                ],
                [
                    'value' => match ($type) {
                        'boolean' => $value ? '1' : '0',
                        'json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        default => (string) $value,
                    },
                    'type' => $type,
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(string $slug): array
    {
        $path = base_path('modules/'.$slug.'/module.json');

        if (! File::isFile($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function log(int $siteId, string $slug, string $action, ?array $before, ?array $after): void
    {
        try {
            SeoLifecycleLog::query()->create([
                'site_id' => $siteId,
                'module_slug' => $slug,
                'action' => $action,
                'actor_id' => auth()->id(),
                'before' => $before,
                'after' => $after,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function clearCaches(int $siteId): void
    {
        foreach (['seo:sitemap', 'seo:modules', 'seo:robots'] as $key) {
            Cache::forget($key);
        }

        // Sitemap chunks are persisted derived data. Invalidate them on every
        // module activation/deactivation so an inactive module cannot remain
        // present in the public sitemap until the next scheduled rebuild.
        try {
            SeoSitemapChunk::query()->where('site_id', $siteId)->delete();
            SeoSitemapRun::query()->where('site_id', $siteId)->delete();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
