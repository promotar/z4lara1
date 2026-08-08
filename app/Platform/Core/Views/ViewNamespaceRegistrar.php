<?php

namespace App\Platform\Core\Views;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Throwable;

class ViewNamespaceRegistrar
{
    public function __construct(
        private readonly PluginViewResolver $pluginViews,
        private readonly CoreViewResolver $coreViews,
    ) {}

    public function register(): void
    {
        try {
            $this->registerGlobalCoreOverridePath();
            $this->registerCoreNamespace();
            $this->registerPluginNamespaces();
        } catch (Throwable $exception) {
            Log::warning('View namespace registration skipped because a resolver failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function registerGlobalCoreOverridePath(): void
    {
        $path = storage_path('app/theme-overrides/core');

        if (is_dir($path)) {
            View::getFinder()->prependLocation($path);
        }
    }

    private function registerCoreNamespace(): void
    {
        $paths = $this->coreViews->existingRoots();
        $override = storage_path('app/theme-overrides/namespaces/core');

        if (is_dir($override)) {
            array_unshift($paths, $override);
        }

        if ($paths !== []) {
            View::replaceNamespace('core', $paths);
        }
    }

    private function registerPluginNamespaces(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        foreach ($this->pluginViews->activePluginViewPaths() as $slug => $path) {
            $paths = [$path];
            $override = storage_path('app/theme-overrides/plugins/'.$slug);

            if (is_dir($override)) {
                array_unshift($paths, $override);
            }

            View::replaceNamespace('plugin-'.$slug, $paths);
        }
    }
}
