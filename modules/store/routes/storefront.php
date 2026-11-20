<?php

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Artwork\Controllers\ArtworkAuthenticityController;
use Modules\Store\Http\Artwork\Controllers\ArtworkMediaController;
use Modules\Store\Http\Artwork\Controllers\ArtworkProductController;
use Modules\Store\Http\Storefront\Controllers\CartController;
use Modules\Store\Http\Storefront\Controllers\ArtworkStorefrontController;
use Modules\Store\Http\Storefront\Controllers\CheckoutController;
use Modules\Store\Http\Storefront\Controllers\CustomerAccountController;
use Modules\Store\Http\Storefront\Controllers\OrderConfirmationController;
use Modules\Store\Http\Storefront\Controllers\ProductDetailController;
use Modules\Store\Http\Storefront\Controllers\ProductQuickViewController;
use Modules\Store\Http\Storefront\Controllers\StorefrontController;
use Modules\Store\Http\Storefront\Controllers\StoreTemplateAssetController;

Route::get('/', [StorefrontController::class, 'index'])->name('index');
Route::get('/assets/templates/{slug}.js', StoreTemplateAssetController::class)
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('template-script');
Route::get('/search', [StorefrontController::class, 'search'])->name('search');
Route::get('/categories/{slug}', [StorefrontController::class, 'category'])->name('categories.show');
Route::get('/brands/{slug}', [StorefrontController::class, 'brand'])->name('brands.show');
Route::get('/artworks', [ArtworkStorefrontController::class, 'index'])->name('artworks.index');
Route::get('/products/{slug}/quick-view', ProductQuickViewController::class)->name('products.quick-view');
Route::get('/products/{slug}', ProductDetailController::class)->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/state', [CartController::class, 'state'])->name('cart.state');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:20,1')->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');

Route::middleware('auth')->group(function (): void {
    Route::post('/checkout/shipping-quote', [CheckoutController::class, 'shippingQuote'])->middleware('throttle:store.shipping-quote')->name('checkout.shipping-quote');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:store.checkout')->name('checkout.store');
    Route::get('/orders/{order}/confirmation', OrderConfirmationController::class)->name('orders.confirmation');

    Route::prefix('seller/artworks')->name('seller.artworks.')->group(function (): void {
        Route::get('/', [ArtworkProductController::class, 'sellerIndex'])->name('index');
        Route::get('/create', [ArtworkProductController::class, 'sellerCreate'])->name('create');
        Route::post('/', [ArtworkProductController::class, 'sellerStore'])->middleware('throttle:20,1')->name('store');
        Route::post('/autosave', [ArtworkProductController::class, 'sellerAutosave'])->middleware('throttle:30,1')->name('autosave.store');
        Route::get('/{artwork}/edit', [ArtworkProductController::class, 'sellerEdit'])->name('edit');
        Route::put('/{artwork}', [ArtworkProductController::class, 'sellerUpdate'])->middleware('throttle:20,1')->name('update');
        Route::post('/{artwork}/autosave', [ArtworkProductController::class, 'sellerAutosave'])->middleware('throttle:30,1')->name('autosave.update');
        Route::get('/{artwork}/media', [ArtworkMediaController::class, 'sellerIndex'])->name('media.index');
        Route::post('/{artwork}/media', [ArtworkMediaController::class, 'sellerUpload'])->middleware('throttle:10,1')->name('media.upload');
        Route::post('/{artwork}/media/reorder', [ArtworkMediaController::class, 'sellerReorder'])->name('media.reorder');
        Route::post('/{artwork}/media/{image}/primary', [ArtworkMediaController::class, 'sellerPrimary'])->name('media.primary');
        Route::post('/{artwork}/media/{image}/replace', [ArtworkMediaController::class, 'sellerReplace'])->middleware('throttle:10,1')->name('media.replace');
        Route::delete('/{artwork}/media/{image}', [ArtworkMediaController::class, 'sellerDelete'])->name('media.delete');
        Route::get('/{artwork}/media/{image}/display', [ArtworkMediaController::class, 'sellerDisplay'])->name('media.display');
        Route::get('/{artwork}/media/{image}/original', [ArtworkMediaController::class, 'sellerOriginal'])->name('media.original');
        Route::get('/{artwork}/authenticity', [ArtworkAuthenticityController::class, 'sellerShow'])->name('authenticity.show');
        Route::post('/{artwork}/authenticity', [ArtworkAuthenticityController::class, 'sellerSave'])->middleware('throttle:10,1')->name('authenticity.save');
        Route::get('/{artwork}/authenticity/evidence/{evidence}', [ArtworkAuthenticityController::class, 'sellerEvidence'])->name('authenticity.evidence');
        Route::post('/{artwork}/duplicate', [ArtworkProductController::class, 'sellerDuplicate'])->middleware('throttle:10,1')->name('duplicate');
        Route::post('/{artwork}/unpublish-for-edit', [ArtworkProductController::class, 'sellerUnpublishForEdit'])->middleware('throttle:5,1')->name('unpublish-for-edit');
        Route::post('/{artwork}/archive', [ArtworkProductController::class, 'sellerArchive'])->middleware('throttle:5,1')->name('archive');
    });
});

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function (): void {
    Route::redirect('/', '/store/account/orders')->name('index');
    Route::get('/overview', [CustomerAccountController::class, 'overview'])->name('overview');
    Route::get('/orders', [CustomerAccountController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [CustomerAccountController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/items/{item}/review', [CustomerAccountController::class, 'storeReview'])->middleware('throttle:10,1')->name('orders.items.reviews.store');
    Route::post('/orders/{order}/reorder', [CustomerAccountController::class, 'reorder'])->middleware('throttle:10,1')->name('orders.reorder');
    Route::post('/orders/{order}/cancel', [CustomerAccountController::class, 'cancel'])->middleware('throttle:10,1')->name('orders.cancel');
    Route::post('/orders/{order}/cancellation-request', [CustomerAccountController::class, 'requestCancellation'])->middleware('throttle:10,1')->name('orders.cancellation-request');
    Route::post('/orders/{order}/return-request', [CustomerAccountController::class, 'requestReturn'])->middleware('throttle:10,1')->name('orders.return-request');
    Route::post('/orders/{order}/return-request/evidence', [CustomerAccountController::class, 'submitReturnEvidence'])->middleware('throttle:10,1')->name('orders.return-request.evidence');
    Route::post('/orders/{order}/return-request/shipment', [CustomerAccountController::class, 'submitReturnShipment'])->middleware('throttle:10,1')->name('orders.return-request.shipment');
    Route::post('/orders/{order}/return-request/refund-destination', [CustomerAccountController::class, 'submitRefundDestination'])->middleware('throttle:10,1')->name('orders.return-request.refund-destination');
    Route::post('/orders/{order}/return-request/refund-received', [CustomerAccountController::class, 'confirmRefundReceived'])->middleware('throttle:10,1')->name('orders.return-request.refund-received');
    Route::delete('/orders/{order}/return-request', [CustomerAccountController::class, 'cancelReturn'])->middleware('throttle:10,1')->name('orders.return-request.cancel');
    Route::get('/orders/{order}/return-attachments/{attachment}', [CustomerAccountController::class, 'returnAttachment'])->name('orders.return-attachments.show');
    Route::get('/orders/{order}/invoice', [CustomerAccountController::class, 'invoice'])->name('orders.invoice');
    Route::get('/addresses', [CustomerAccountController::class, 'addresses'])->name('addresses.index');
    Route::post('/addresses', [CustomerAccountController::class, 'storeAddress'])->middleware('throttle:30,1')->name('addresses.store');
    Route::put('/addresses/{address}', [CustomerAccountController::class, 'updateAddress'])->middleware('throttle:30,1')->name('addresses.update');
    Route::patch('/addresses/{address}/default', [CustomerAccountController::class, 'defaultAddress'])->name('addresses.default');
    Route::delete('/addresses/{address}', [CustomerAccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::get('/personal-information', [CustomerAccountController::class, 'personal'])->name('personal');
    Route::put('/personal-information', [CustomerAccountController::class, 'updatePersonal'])->middleware('throttle:12,1')->name('personal.update');
    Route::get('/profile-avatar', [CustomerAccountController::class, 'avatar'])->name('avatar');
});
