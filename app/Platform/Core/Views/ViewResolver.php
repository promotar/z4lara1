<?php

namespace App\Platform\Core\Views;

use App\Platform\Core\Models\Plugin;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewResolver
{
    public function __construct(
        private readonly PluginViewResolver $pluginViews,
        private readonly CoreViewResolver $coreViews,
        private readonly PluginRepository $plugins,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $context
     */
    public function resolve(string $view, array $context = []): ?string
    {
        $pluginSlug = $context['plugin'] ?? $context['plugin_slug'] ?? null;

        if (is_string($pluginSlug) && trim($pluginSlug) !== '') {
            return $this->resolvePluginView($pluginSlug, $view);
        }

        return $this->resolveCoreView($view);
    }

    public function resolvePluginView(string $pluginSlug, string $view): ?string
    {
        try {
            $plugin = $this->plugins->findBySlug($pluginSlug);

            if ($plugin?->status !== Plugin::STATUS_ACTIVE) {
                return null;
            }

            return $this->pluginViews->resolve($pluginSlug, $view);
        } catch (Throwable $exception) {
            Log::warning('Plugin view resolution skipped unsafe or broken path.', [
                'plugin' => $pluginSlug,
                'view' => $view,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function resolveCoreView(string $view): ?string
    {
        try {
            return $this->coreViews->resolve($view);
        } catch (Throwable $exception) {
            Log::warning('Core view resolution skipped unsafe or broken path.', [
                'view' => $view,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function exists(string $view, array $context = []): bool
    {
        return $this->resolve($view, $context) !== null;
    }
}
