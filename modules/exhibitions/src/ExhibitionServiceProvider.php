<?php

namespace Modules\Exhibition;

use App\Platform\Core\Contracts\UserDeletionParticipant as UserDeletionParticipantContract;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Seo\SeoRegistry;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Exhibition\Application\Applications\ArtistAccessGate;
use Modules\Exhibition\Application\PublicSite\ArtistPortalNavigation;
use Modules\Exhibition\Application\Readiness\DefaultExhibitionReadiness;
use Modules\Exhibition\Application\Store\StoreLifecycleProcessor;
use Modules\Exhibition\Application\Store\Ve03AvailabilityPublisher;
use Modules\Exhibition\Console\Commands\ExpireVe03PlacementHoldsCommand;
use Modules\Exhibition\Console\Commands\GenerateVe03ArtworkDerivativesCommand;
use Modules\Exhibition\Console\Commands\ImportVe03AssetVersionCommand;
use Modules\Exhibition\Console\Commands\PreviewVe03AvailabilityCommand;
use Modules\Exhibition\Console\Commands\ProcessDueNotificationsCommand;
use Modules\Exhibition\Console\Commands\ReconcileStoreLifecycle;
use Modules\Exhibition\Console\Commands\ReleaseExpiredInventoryHoldsCommand;
use Modules\Exhibition\Console\Commands\SyncApprovedArtworksCommand;
use Modules\Exhibition\Contracts\Artists\ArtistIdentityRepository;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use Modules\Exhibition\Contracts\Payments\V1\PayoutOutcomeReceiver;
use Modules\Exhibition\Contracts\Readiness\ExhibitionReadiness;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationGateway;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleGateway;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleRecord;
use Modules\Exhibition\Events\V1\ExhibitionRuntimeRegistered;
use Modules\Exhibition\Http\Middleware\RedirectArtistFromStaffDashboard;
use Modules\Exhibition\Infrastructure\Identity\EloquentCoreUserGateway;
use Modules\Exhibition\Infrastructure\Lifecycle\ActivationAuditSynchronizer;
use Modules\Exhibition\Infrastructure\Payments\SettlementPayoutOutcomeReceiver;
use Modules\Exhibition\Infrastructure\Persistence\DatabaseArtistIdentityRepository;
use Modules\Exhibition\Infrastructure\Persistence\DatabaseAuditRecorder;
use Modules\Exhibition\Infrastructure\Store\StoreArtworkCommerceGateway;
use Modules\Exhibition\Infrastructure\Store\StoreArtworkPublicationGateway;
use Modules\Exhibition\Infrastructure\Store\StoreExternalSellableLifecycleGateway;
use Modules\Exhibition\Listeners\BeginApplicationReviewAfterEmailVerification;
use Modules\Exhibition\Listeners\BeginApplicationReviewAfterLogin;
use Modules\Exhibition\Support\ExhibitionAccountSectionProvider;
use Modules\Exhibition\Support\ExhibitionUserDeletionParticipant;

require_once __DIR__.'/Support/autoload.php';

final class ExhibitionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $root = dirname(__DIR__);

        $this->mergeConfigFrom($root.'/config/exhibitions.php', 'exhibitions');
        $this->app->singleton(AuditRecorder::class, DatabaseAuditRecorder::class);
        $this->app->singleton(CoreUserGateway::class, EloquentCoreUserGateway::class);
        $this->app->singleton(ArtistIdentityRepository::class, DatabaseArtistIdentityRepository::class);
        $this->app->singleton(ExhibitionReadiness::class, DefaultExhibitionReadiness::class);
        $this->app->singleton(ArtworkPublicationGateway::class, StoreArtworkPublicationGateway::class);
        $this->app->singleton(ArtworkCommerceGateway::class, StoreArtworkCommerceGateway::class);
        $this->app->singleton(StoreLifecycleGateway::class, StoreExternalSellableLifecycleGateway::class);
        $this->app->singleton(PayoutOutcomeReceiver::class, SettlementPayoutOutcomeReceiver::class);
        $this->app->singleton(ArtistAccessGate::class);
        $this->app->singleton(ExhibitionAccountSectionProvider::class);
        $this->app->singleton(ExhibitionUserDeletionParticipant::class);
        $this->app->tag(ExhibitionUserDeletionParticipant::class, UserDeletionParticipantContract::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireVe03PlacementHoldsCommand::class,
                GenerateVe03ArtworkDerivativesCommand::class,
                ProcessDueNotificationsCommand::class,
                PreviewVe03AvailabilityCommand::class,
                ImportVe03AssetVersionCommand::class,
                ReconcileStoreLifecycle::class,
                ReleaseExpiredInventoryHoldsCommand::class,
                SyncApprovedArtworksCommand::class,
            ]);

            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->command('exhibitions:store-lifecycle:reconcile --limit=500')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->onOneServer();
                $schedule->command('exhibitions:artworks:sync-approved --limit=100')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->onOneServer();
                $schedule->command('exhibitions:notifications:process-due --limit=500')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->onOneServer();
                $schedule->command('exhibitions:inventory:release-expired --limit=500')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->onOneServer();
                $schedule->command('exhibitions:ve03:expire-placement-holds --limit=500')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->onOneServer();
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        $root = dirname(__DIR__);

        // Keep the asset cache-busting version in sync with the module manifest.
        $manifestVersion = json_decode((string) @file_get_contents($root.'/module.json'), true)['version'] ?? null;

        if (is_string($manifestVersion) && trim($manifestVersion) !== '') {
            config(['exhibitions.version' => trim($manifestVersion)]);
        }

        $this->app->make(HookManager::class)->addFilter(
            'frontend.account.sections',
            [$this->app->make(ExhibitionAccountSectionProvider::class), 'append'],
            priority: 40,
            acceptedArgs: 2,
        );

        $router = $this->app->make(Router::class);
        $webMiddleware = array_values(array_filter(
            $router->getMiddlewareGroups()['web'] ?? [],
            static fn (string $middleware): bool => $middleware !== RedirectArtistFromStaffDashboard::class,
        ));
        $permissionGate = array_search(
            'App\\Http\\Middleware\\EnforceRoutePermission',
            $webMiddleware,
            true,
        );
        array_splice(
            $webMiddleware,
            $permissionGate === false ? count($webMiddleware) : $permissionGate,
            0,
            [RedirectArtistFromStaffDashboard::class],
        );
        $router->middlewareGroup('web', $webMiddleware);

        $this->loadViewsFrom($root.'/resources/views', 'exhibitions');
        $this->loadTranslationsFrom($root.'/resources/lang', 'exhibitions');

        require_once $root.'/src/Seo/ExhibitionSeoContributor.php';
        require_once $root.'/src/Seo/ExhibitionSitemapContributor.php';

        $seoRegistry = $this->app->make(SeoRegistry::class);
        $seoRegistry->add($this->app->make(Seo\ExhibitionSeoContributor::class), priority: 50);
        $seoRegistry->addSitemap($this->app->make(Seo\ExhibitionSitemapContributor::class), priority: 50);
        // Module installation/upgrade owns migration execution. Registering an
        // active provider must not make ordinary platform migrations replay a
        // module's schema outside its lifecycle.

        View::composer('layouts.navigation', function ($view): void {
            $user = auth()->user();
            if ($user === null || $user->hasAnyRole(['super-admin', 'admin', 'staff', 'employee'])) {
                return;
            }

            $view->with('moduleNavigation', $this->app->make(ArtistPortalNavigation::class)->forUser((int) $user->getAuthIdentifier()));
        });

        $this->app->make(ActivationAuditSynchronizer::class)->sync();

        Event::listen(Verified::class, BeginApplicationReviewAfterEmailVerification::class);
        Event::listen(Login::class, BeginApplicationReviewAfterLogin::class);

        Event::listen('Modules\\Store\\Events\\ExternalSellableLifecycleChanged', function (object $event): void {
            $this->app->make(StoreLifecycleProcessor::class)->process(
                StoreLifecycleRecord::fromExternal(
                    isset($event->record) && is_object($event->record) ? $event->record : $event,
                ),
            );
            if (($event->ownerCode ?? null) === 'exhibitions') {
                $this->app->make(Ve03AvailabilityPublisher::class)->publish(
                    (string) $event->externalId,
                    (int) ($event->sequence ?? now()->getTimestampMs()),
                );
            }
        });

        Event::listen('Modules\\Store\\Events\\ExternalSellableAvailabilityChanged', function (object $event): void {
            if (($event->ownerCode ?? null) !== 'exhibitions') {
                return;
            }
            $this->app->make(Ve03AvailabilityPublisher::class)->publish(
                (string) $event->externalId,
                (int) ($event->version ?? now()->getTimestampMs()),
            );
        });

        Event::dispatch(new ExhibitionRuntimeRegistered((string) config('exhibitions.version', '1.6.7')));
    }
}
