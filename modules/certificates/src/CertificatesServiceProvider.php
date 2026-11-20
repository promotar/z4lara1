<?php

namespace Modules\Certificates;

use App\Platform\Core\Hooks\HookManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Services\AccountSectionProvider;
use Modules\Certificates\Services\IssuerAccessService;
use Modules\Certificates\Support\CertificateRoutes;

class CertificatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IssuerAccessService::class);
        $this->app->singleton(AccountSectionProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'certificates');

        Gate::define('certificates.admin-menu', static fn ($user): bool =>
            $user->hasAnyRole(['super-admin', 'admin']) && $user->can('certificates.manage'));

        Certificate::saved(static fn () => CertificateRoutes::refresh());
        Certificate::deleted(static fn () => CertificateRoutes::refresh());
        Certificate::restored(static fn () => CertificateRoutes::refresh());

        $this->app->make(HookManager::class)->addFilter(
            'frontend.account.sections',
            [$this->app->make(AccountSectionProvider::class), 'append'],
            priority: 35,
            acceptedArgs: 2,
        );
    }
}
