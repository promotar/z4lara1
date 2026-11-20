<?php

namespace Modules\Seo;

use Modules\Seo\Services\SeoModuleSyncManager;
use Throwable;

/**
 * The SEO module's own lifecycle callbacks. Data cleanup for other modules is
 * handled by hooks.php, not by this class.
 */
final class SeoLifecycle
{
    public function install(): void
    {
        $this->ensureInitialized();
    }

    public function activate(): void
    {
        $this->ensureInitialized();
    }

    public function deactivate(): void
    {
        try {
            app(SeoModuleSyncManager::class)->deactivate('seo');
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function purge(): void
    {
        // The platform drops the declared seo_* tables after this callback.
        // Nothing else is generated outside the database.
    }

    private function ensureInitialized(): void
    {
        try {
            $sync = app(SeoModuleSyncManager::class);
            $sync->activate('seo');
            $sync->reconcile();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
