<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use RuntimeException;

class AdminThemeManager
{
    public const DEFAULT_SLUG = 'admin-theme';

    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly PluginMenuRegistry $menus,
        private readonly PluginRuntimeRegistry $runtime,
        private readonly PluginCacheCleaner $cache,
    ) {
        //
    }

    public function guardManualDeactivation(Plugin $plugin): void
    {
        if (! $plugin->isDefaultAdminTheme()) {
            return;
        }

        if ($this->activeAdminThemesExcept($plugin->slug)->isNotEmpty()) {
            return;
        }

        throw new RuntimeException('The default admin theme cannot be deactivated while no other admin theme is active.');
    }

    public function afterActivation(Plugin $plugin): Plugin
    {
        if (! $plugin->isAdminTheme()) {
            return $plugin;
        }

        $this->disableOtherAdminThemes($plugin->slug);

        return $plugin->refresh();
    }

    public function afterDeactivation(Plugin $plugin): void
    {
        if (! $plugin->isAdminTheme()) {
            return;
        }

        if ($this->activeAdminThemes()->isNotEmpty()) {
            return;
        }

        $default = $this->plugins->findBySlug(self::DEFAULT_SLUG);

        if (! $default) {
            throw new RuntimeException('No active admin theme remains and the default admin-theme plugin is not installed.');
        }

        $this->enableTheme($default);
    }

    public function ensureDefaultWhenNoAdminThemeActive(): void
    {
        if ($this->activeAdminThemes()->isNotEmpty()) {
            return;
        }

        $default = $this->plugins->findBySlug(self::DEFAULT_SLUG);

        if ($default) {
            $this->enableTheme($default);
        }
    }

    private function disableOtherAdminThemes(string $activeSlug): void
    {
        Plugin::query()
            ->where('slug', '!=', $activeSlug)
            ->where('status', Plugin::STATUS_ACTIVE)
            ->get()
            ->filter(fn (Plugin $plugin): bool => $plugin->isAdminTheme())
            ->each(fn (Plugin $plugin): mixed => $this->disableTheme($plugin));
    }

    private function enableTheme(Plugin $plugin): void
    {
        $plugin = $this->plugins->markActivated($plugin);
        $this->menus->show($plugin->slug);
        $this->runtime->enable($plugin->slug);
        $this->flushRuntimeGate($plugin->slug);
        $this->cache->clear();
    }

    private function disableTheme(Plugin $plugin): void
    {
        $plugin = $this->plugins->markDisabled($plugin);
        $this->menus->hide($plugin->slug);
        $this->runtime->disable($plugin->slug);
        $this->flushRuntimeGate($plugin->slug);
    }

    private function activeAdminThemes()
    {
        return Plugin::query()
            ->where('status', Plugin::STATUS_ACTIVE)
            ->get()
            ->filter(fn (Plugin $plugin): bool => $plugin->isAdminTheme())
            ->values();
    }

    private function activeAdminThemesExcept(string $slug)
    {
        return $this->activeAdminThemes()
            ->reject(fn (Plugin $plugin): bool => $plugin->slug === $slug)
            ->values();
    }

    private function flushRuntimeGate(string $slug): void
    {
        if (app()->bound(PluginRuntimeGate::class)) {
            app(PluginRuntimeGate::class)->flush($slug);
        }
    }
}
