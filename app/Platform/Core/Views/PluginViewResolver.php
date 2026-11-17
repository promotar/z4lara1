<?php

namespace App\Platform\Core\Views;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use App\Platform\Core\Services\PluginRuntimeGate;

class PluginViewResolver
{
    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly ViewPathGuard $guard,
        private readonly PluginRuntimeGate $gate,
    ) {
        //
    }

    public function resolve(string $pluginSlug, string $view): ?string
    {
        $plugin = $this->activePlugin($pluginSlug);

        if ($plugin === null) {
            return null;
        }

        return $this->guard->pathInside(
            (string) $plugin->path,
            'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.$this->guard->viewToRelativePath($view),
        );
    }

    /**
     * @return array<string, string>
     */
    public function activePluginViewPaths(): array
    {
        $paths = [];

        foreach ($this->plugins->findActive() as $plugin) {
            if (! $this->gate->allows($plugin->slug)) {
                continue;
            }

            $path = $this->guard->directoryInside((string) $plugin->path, 'resources'.DIRECTORY_SEPARATOR.'views');

            if ($path !== null) {
                $paths[$plugin->slug] = $path;
            }
        }

        return $paths;
    }

    private function activePlugin(string $slug): ?Plugin
    {
        $plugin = $this->plugins->findBySlug($slug);

        return $plugin?->status === Plugin::STATUS_ACTIVE && $this->gate->allows($slug) ? $plugin : null;
    }
}
