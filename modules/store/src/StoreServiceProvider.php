<?php

namespace Modules\Store;

use App\Platform\Core\Contracts\UserDeletionParticipant as UserDeletionParticipantContract;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Seo\SeoRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Store\Application\Capabilities\ManifestStoreCapabilities;
use Modules\Store\Application\Inventory\LowStockNotificationService;
use Modules\Store\Application\Orders\OrderNotificationService;
use Modules\Store\Application\Orders\StoreAdminNotificationService;
use Modules\Store\Application\Readiness\DefaultStoreReadiness;
use Modules\Store\Application\Settings\StoreSettingsService;
use Modules\Store\Console\Commands\ExpireCodReservationsCommand;
use Modules\Store\Contracts\Capabilities\StoreCapabilities;
use Modules\Store\Contracts\Catalog\ProductReadService;
use Modules\Store\Contracts\Categories\CategoryReadService;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableGateway;
use Modules\Store\Contracts\Integrations\V1\ExternalSellableLifecycleFeed;
use Modules\Store\Contracts\Inventory\InventoryReadService;
use Modules\Store\Contracts\Payments\PaymentMethodRegistry;
use Modules\Store\Contracts\Pricing\PriceReadService;
use Modules\Store\Contracts\Readiness\StoreReadiness;
use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Catalog\CategoryTreeService;
use Modules\Store\Domain\Catalog\DefaultProductTypeRegistry;
use Modules\Store\Domain\Catalog\ProductAttributeRepository;
use Modules\Store\Domain\Catalog\ProductPublicationPolicy;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductTypeRegistry;
use Modules\Store\Domain\Catalog\PublicationFactsProvider;
use Modules\Store\Domain\Inventory\Events\InventoryAdjusted;
use Modules\Store\Domain\Inventory\Events\InventoryReservationCommitted;
use Modules\Store\Domain\Inventory\InventoryRepository;
use Modules\Store\Domain\Media\MediaUploadValidationService;
use Modules\Store\Domain\Media\ProductMediaRepository;
use Modules\Store\Domain\Media\ProductMediaStorage;
use Modules\Store\Domain\Payments\CashOnDeliveryPaymentMethod;
use Modules\Store\Domain\Pricing\PriceResolver;
use Modules\Store\Domain\Pricing\PricingRepository;
use Modules\Store\Domain\Promotions\DefaultPromotionConditionRegistry;
use Modules\Store\Domain\Promotions\PromotionConditionRegistry;
use Modules\Store\Domain\Promotions\PromotionRepository;
use Modules\Store\Domain\Promotions\PromotionResolver;
use Modules\Store\Domain\Settings\StoreSettingsRepository;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\DomainEventPublisher;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;
use Modules\Store\Events\OrderPlaced;
use Modules\Store\Events\OrderStatusChanged;
use Modules\Store\Events\StoreRuntimeRegistered;
use Modules\Store\Infrastructure\Clock\SystemClock;
use Modules\Store\Infrastructure\Events\LaravelDomainEventPublisher;
use Modules\Store\Infrastructure\Identity\UlidPublicIdGenerator;
use Modules\Store\Infrastructure\Integrations\DatabaseExternalSellableGateway;
use Modules\Store\Infrastructure\Integrations\DatabaseExternalSellableLifecycleFeed;
use Modules\Store\Infrastructure\Media\LaravelProductMediaStorage;
use Modules\Store\Infrastructure\Payments\InMemoryPaymentMethodRegistry;
use Modules\Store\Infrastructure\Persistence\Catalog\DatabaseCategoryRepository;
use Modules\Store\Infrastructure\Persistence\Catalog\DatabaseProductAttributeRepository;
use Modules\Store\Infrastructure\Persistence\Catalog\DatabaseProductRepository;
use Modules\Store\Infrastructure\Persistence\Catalog\DatabasePublicationFactsProvider;
use Modules\Store\Infrastructure\Persistence\Inventory\DatabaseInventoryRepository;
use Modules\Store\Infrastructure\Persistence\LaravelTransactionManager;
use Modules\Store\Infrastructure\Persistence\Media\DatabaseProductMediaRepository;
use Modules\Store\Infrastructure\Persistence\Pricing\DatabasePricingRepository;
use Modules\Store\Infrastructure\Persistence\Promotions\DatabasePromotionRepository;
use Modules\Store\Infrastructure\Persistence\Settings\DatabaseStoreSettingsRepository;
use Modules\Store\Infrastructure\ReadModel\DatabaseCategoryReadService;
use Modules\Store\Infrastructure\ReadModel\DatabaseInventoryReadService;
use Modules\Store\Infrastructure\ReadModel\DatabasePriceReadService;
use Modules\Store\Infrastructure\ReadModel\DatabaseProductReadService;
use Modules\Store\Support\StoreAccountSectionProvider;
use Modules\Store\Support\StoreModule;
use Modules\Store\Support\StoreUserDeletionParticipant;

final class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $root = dirname(__DIR__);

        $this->mergeConfigFrom($root.'/config/store.php', 'store');

        $this->app->singleton(StoreAccountSectionProvider::class);
        $this->app->singleton(StoreUserDeletionParticipant::class);
        $this->app->tag(StoreUserDeletionParticipant::class, UserDeletionParticipantContract::class);

        $this->app->singleton(StoreCapabilities::class, fn (): StoreCapabilities => new ManifestStoreCapabilities(StoreModule::CAPABILITIES));

        $this->app->singleton(PaymentMethodRegistry::class, function (): PaymentMethodRegistry {
            $registry = new InMemoryPaymentMethodRegistry(StoreModule::DEFAULT_PAYMENT_METHOD);
            $settings = $this->app->make(StoreSettingsService::class)->current();
            $registry->register(new CashOnDeliveryPaymentMethod(
                (bool) $settings['cod_enabled'],
                (string) $settings['cod_label'],
            ));

            return $registry;
        });

        $this->app->singleton(StoreReadiness::class, DefaultStoreReadiness::class);
        $this->app->singleton(ExternalSellableGateway::class, DatabaseExternalSellableGateway::class);
        $this->app->singleton(DatabaseExternalSellableLifecycleFeed::class);
        $this->app->singleton(
            ExternalSellableLifecycleFeed::class,
            fn (): ExternalSellableLifecycleFeed => $this->app->make(DatabaseExternalSellableLifecycleFeed::class),
        );
        $this->app->singleton(PublicIdGenerator::class, UlidPublicIdGenerator::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(DomainEventPublisher::class, LaravelDomainEventPublisher::class);
        $this->app->singleton(ProductTypeRegistry::class, DefaultProductTypeRegistry::class);
        $this->app->singleton(ProductRepository::class, DatabaseProductRepository::class);
        $this->app->singleton(ProductAttributeRepository::class, DatabaseProductAttributeRepository::class);
        $this->app->singleton(PublicationFactsProvider::class, DatabasePublicationFactsProvider::class);
        $this->app->singleton(ProductPublicationPolicy::class, fn (): ProductPublicationPolicy => new ProductPublicationPolicy((bool) config('store.catalog.require_primary_media_for_publication', false)));
        $this->app->singleton(CategoryTreeService::class, fn (): CategoryTreeService => new CategoryTreeService((int) config('store.catalog.maximum_category_depth', 8)));
        $this->app->singleton(CategoryRepository::class, DatabaseCategoryRepository::class);
        $this->app->singleton(ProductMediaRepository::class, DatabaseProductMediaRepository::class);
        $this->app->singleton(ProductMediaStorage::class, LaravelProductMediaStorage::class);
        $this->app->singleton(MediaUploadValidationService::class, fn (): MediaUploadValidationService => new MediaUploadValidationService(
            (int) config('store.media.maximum_upload_bytes', 15_728_640),
            (int) config('store.media.maximum_image_dimension', 12_000),
        ));
        $this->app->singleton(PriceResolver::class);
        $this->app->singleton(PricingRepository::class, DatabasePricingRepository::class);
        $this->app->singleton(PromotionResolver::class);
        $this->app->singleton(PromotionRepository::class, DatabasePromotionRepository::class);
        $this->app->singleton(PromotionConditionRegistry::class, DefaultPromotionConditionRegistry::class);
        $this->app->singleton(InventoryRepository::class, DatabaseInventoryRepository::class);
        $this->app->singleton(StoreSettingsRepository::class, DatabaseStoreSettingsRepository::class);
        $this->app->singleton(ProductReadService::class, DatabaseProductReadService::class);
        $this->app->singleton(CategoryReadService::class, DatabaseCategoryReadService::class);
        $this->app->singleton(PriceReadService::class, DatabasePriceReadService::class);
        $this->app->singleton(InventoryReadService::class, DatabaseInventoryReadService::class);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);

        $this->app->make(HookManager::class)->addFilter(
            'frontend.account.sections',
            [$this->app->make(StoreAccountSectionProvider::class), 'append'],
            priority: 30,
            acceptedArgs: 2,
        );

        $this->registerRateLimiters();

        $this->loadViewsFrom($root.'/resources/views', 'store');
        $this->loadTranslationsFrom($root.'/resources/lang', 'store');
        $this->loadJsonTranslationsFrom($root.'/resources/lang');
        $this->loadMigrationsFrom($root.'/database/migrations');

        require_once $root.'/src/Seo/StoreSeoContributor.php';
        require_once $root.'/src/Seo/StoreSitemapContributor.php';

        $seoRegistry = $this->app->make(SeoRegistry::class);
        $seoRegistry->add($this->app->make(Seo\StoreSeoContributor::class), priority: 40);
        $seoRegistry->addSitemap($this->app->make(Seo\StoreSitemapContributor::class), priority: 40);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireCodReservationsCommand::class]);
        }
        Schedule::command('store:orders:expire-cod')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        Event::listen(OrderPlaced::class, function (OrderPlaced $event): void {
            $this->app->make(OrderNotificationService::class)->send($event->orderPublicId);
            $this->app->make(StoreAdminNotificationService::class)->send(
                'new_order',
                "New Store order {$event->orderNumber}",
                "A new order {$event->orderNumber} was received.",
            );
        });
        Event::listen(OrderStatusChanged::class, function (OrderStatusChanged $event): void {
            $this->app->make(OrderNotificationService::class)->send($event->orderPublicId);
        });
        Event::listen(InventoryAdjusted::class, function (InventoryAdjusted $event): void {
            $this->app->make(LowStockNotificationService::class)->forInventoryItem($event->inventoryItemPublicId);
        });
        Event::listen(InventoryReservationCommitted::class, function (InventoryReservationCommitted $event): void {
            $this->app->make(LowStockNotificationService::class)->forReservation($event->reservationPublicId);
        });

        Event::dispatch(new StoreRuntimeRegistered('12.10.2'));
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('store.shipping-quote', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitIdentity($request)));

        RateLimiter::for('store.checkout', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($this->rateLimitIdentity($request)));
    }

    private function rateLimitIdentity(Request $request): string
    {
        if ($request->user() !== null) {
            return 'user:'.(string) $request->user()->getAuthIdentifier();
        }

        return 'ip:'.(string) $request->ip();
    }
}
