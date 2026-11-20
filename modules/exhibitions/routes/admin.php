<?php

use Illuminate\Support\Facades\Route;
use Modules\Exhibition\Http\Admin\Controllers\AdminWorkspaceController;
use Modules\Exhibition\Http\Admin\Controllers\AgreementTemplateAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ApplicationAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtistAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtistCommissionTermsAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkAuthenticityAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkInventoryAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkPricingAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkPublicationAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkReviewAdminController;
use Modules\Exhibition\Http\Admin\Controllers\ArtworkTransferAdminController;
use Modules\Exhibition\Http\Admin\Controllers\AssetController;
use Modules\Exhibition\Http\Admin\Controllers\CommunicationAdminController;
use Modules\Exhibition\Http\Admin\Controllers\DisputeAdminController;
use Modules\Exhibition\Http\Admin\Controllers\FulfillmentAdminController;
use Modules\Exhibition\Http\Admin\Controllers\OverviewController;
use Modules\Exhibition\Http\Admin\Controllers\ReportAdminController;
use Modules\Exhibition\Http\Admin\Controllers\SecurityAdminController;
use Modules\Exhibition\Http\Admin\Controllers\SettlementAdminController;
use Modules\Exhibition\Http\Admin\Controllers\Ve03AssetPipelineController;
use Modules\Exhibition\Http\Admin\Controllers\VerificationAdminController;
use Modules\Exhibition\Http\Admin\Controllers\VirtualExhibitionAdminController;

Route::get('/assets/exhibitions.css', [AssetController::class, 'stylesheet'])->name('assets.stylesheet');
Route::get('/', [OverviewController::class, 'index'])->name('overview');
Route::get('/health', [OverviewController::class, 'health'])->name('health');
Route::middleware('permission:exhibitions.virtual_rooms.view')->group(function (): void {
    Route::get('/virtual/ve03/assets', [Ve03AssetPipelineController::class, 'index'])->name('virtual.ve03.assets.index');
    Route::get('/virtual/ve03/assets/{version}/preview', [Ve03AssetPipelineController::class, 'preview'])->name('virtual.ve03.assets.preview');
    Route::get('/virtual/ve03/assets/{version}/preview/manifest.json', [Ve03AssetPipelineController::class, 'previewManifest'])->name('virtual.ve03.assets.preview-manifest');
    Route::get('/virtual/ve03/assets/{version}/{asset}.glb', [Ve03AssetPipelineController::class, 'asset'])
        ->whereIn('asset', ['environment', 'collision'])->name('virtual.ve03.assets.asset');
    Route::get('/virtual', [Ve03AssetPipelineController::class, 'halls'])->name('virtual.rooms.index');
    Route::get('/virtual/artworks', [VirtualExhibitionAdminController::class, 'publishedArtworks'])->name('virtual.artworks.index');
    Route::get('/virtual/placements', [VirtualExhibitionAdminController::class, 'requests'])->name('virtual.requests.index');
    Route::get('/virtual/placements/{placement}', [VirtualExhibitionAdminController::class, 'review'])->name('virtual.requests.review');
    Route::get('/virtual/rooms/{room}/panorama', [VirtualExhibitionAdminController::class, 'panorama'])->name('virtual.rooms.panorama');
});
Route::middleware('permission:exhibitions.virtual_rooms.manage')->group(function (): void {
    Route::get('/virtual/ve03/assets/{version}/edit', [Ve03AssetPipelineController::class, 'edit'])->name('virtual.ve03.assets.edit');
    Route::put('/virtual/ve03/assets/{version}', [Ve03AssetPipelineController::class, 'update'])->middleware('throttle:30,1')->name('virtual.ve03.assets.update');
    Route::post('/virtual/ve03/assets/upload', [Ve03AssetPipelineController::class, 'upload'])->middleware('throttle:20,10')->name('virtual.ve03.assets.upload');
    Route::post('/virtual/ve03/assets/build', [Ve03AssetPipelineController::class, 'uploadBuild'])->middleware('throttle:20,10')->name('virtual.ve03.assets.build');
    Route::post('/virtual/ve03/assets/import', [Ve03AssetPipelineController::class, 'importBuild'])->middleware('throttle:20,10')->name('virtual.ve03.assets.import');
    Route::post('/virtual/ve03/assets/{version}/duplicate', [Ve03AssetPipelineController::class, 'duplicate'])->middleware('throttle:10,10')->name('virtual.ve03.assets.duplicate');
    Route::get('/virtual/rooms/create', [VirtualExhibitionAdminController::class, 'create'])->name('virtual.rooms.create');
    Route::post('/virtual/rooms', [VirtualExhibitionAdminController::class, 'store'])->middleware('throttle:20,1')->name('virtual.rooms.store');
    Route::get('/virtual/rooms/{room}/edit', [VirtualExhibitionAdminController::class, 'edit'])->name('virtual.rooms.edit');
    Route::put('/virtual/rooms/{room}', [VirtualExhibitionAdminController::class, 'update'])->middleware('throttle:30,1')->name('virtual.rooms.update');
    Route::post('/virtual/rooms/{room}/panorama', [VirtualExhibitionAdminController::class, 'uploadPanorama'])->middleware('throttle:10,1')->name('virtual.rooms.panorama.store');
});
Route::middleware('permission:exhibitions.virtual_slots.manage')->group(function (): void {
    Route::get('/virtual/rooms/{room}/layout', [VirtualExhibitionAdminController::class, 'layout'])->name('virtual.rooms.layout');
    Route::post('/virtual/rooms/{room}/slots', [VirtualExhibitionAdminController::class, 'createSlot'])->middleware('throttle:60,1')->name('virtual.slots.store');
    Route::put('/virtual/slots/{slot}', [VirtualExhibitionAdminController::class, 'updateSlot'])->middleware('throttle:60,1')->name('virtual.slots.update');
    Route::delete('/virtual/slots/{slot}', [VirtualExhibitionAdminController::class, 'deleteSlot'])->middleware('throttle:30,1')->name('virtual.slots.destroy');
});
Route::post('/virtual/rooms/{room}/state', [VirtualExhibitionAdminController::class, 'state'])->middleware(['permission:exhibitions.virtual_rooms.publish', 'throttle:20,1'])->name('virtual.rooms.state');
Route::post('/virtual/ve03/assets/{version}/publish', [Ve03AssetPipelineController::class, 'publish'])->middleware(['permission:exhibitions.virtual_rooms.publish', 'throttle:20,10'])->name('virtual.ve03.assets.publish');
Route::delete('/virtual/ve03/assets/{version}', [Ve03AssetPipelineController::class, 'destroy'])->middleware(['permission:exhibitions.virtual_rooms.publish', 'throttle:10,10'])->name('virtual.ve03.assets.destroy');
Route::post('/virtual/placements/{placement}/decision', [VirtualExhibitionAdminController::class, 'decide'])->middleware(['permission:exhibitions.virtual.review', 'throttle:30,1'])->name('virtual.requests.decide');
Route::post('/virtual/ve03/placements/{placement}/remove', [VirtualExhibitionAdminController::class, 'removeDirectPlacement'])->middleware(['permission:exhibitions.virtual.review', 'throttle:30,1'])->name('virtual.ve03.placements.remove');
Route::post('/virtual/ve03/republish-requests/{republishRequest}/decision', [VirtualExhibitionAdminController::class, 'reviewRepublishRequest'])->middleware(['permission:exhibitions.virtual.review', 'throttle:30,1'])->name('virtual.ve03.republish-requests.decision');
Route::get('/workspace/{area}', [AdminWorkspaceController::class, 'workspace'])->whereIn('area', ['artists', 'artworks', 'authenticity', 'pricing', 'fulfillment', 'finance', 'support'])->name('workspace');
Route::middleware('permission:exhibitions.manage_settings')->group(function (): void {
    Route::get('/settings', [AdminWorkspaceController::class, 'settings'])->name('settings');
    Route::get('/settings/commission', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'commission-earnings')->name('settings.commission');
    Route::get('/settings/payout-policy', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'settlement-policy')->name('settings.payout-policy');
    Route::get('/settings/artwork-rules', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.artwork-rules');
    Route::get('/settings/notifications', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'notifications')->name('settings.notifications');

    // Preserve old bookmarks while presenting only the four focused settings pages.
    Route::get('/settings/sales-commission', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'commission-earnings');
    Route::get('/settings/general', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.general');
    Route::get('/settings/artist-applications', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.artist-applications');
    Route::get('/settings/artwork-submission', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.artwork-submission');
    Route::get('/settings/review-publishing', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.review-publishing');
    Route::get('/settings/store-integration', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.store-integration');
    Route::get('/settings/media-authenticity', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.media-authenticity');
    Route::get('/settings/lifecycle-data', [AdminWorkspaceController::class, 'settings'])->defaults('section', 'artwork-rules')->name('settings.lifecycle-data');
    Route::post('/settings', [AdminWorkspaceController::class, 'updateSetting'])->middleware('throttle:30,1')->name('settings.update');
    Route::post('/settings/reset-section', [AdminWorkspaceController::class, 'resetSettingsGroup'])->middleware('throttle:10,1')->name('settings.reset-section');
});
Route::post('/settings/purge', [AdminWorkspaceController::class, 'purgeSettings'])
    ->middleware(['permission:exhibitions.purge_data', 'throttle:3,10'])
    ->name('settings.purge');
Route::middleware('permission:exhibitions.manage')->group(function (): void {
    Route::get('/artists/create', [ArtistAdminController::class, 'create'])->name('artists.create');
    Route::post('/artists', [ArtistAdminController::class, 'store'])->middleware('throttle:20,1')->name('artists.store');
    Route::post('/artists/{artist}/activate', [ArtistAdminController::class, 'activate'])->middleware('throttle:20,1')->name('artists.activate');
    Route::post('/artists/{artist}/deactivate', [ArtistAdminController::class, 'deactivate'])->middleware('throttle:10,1')->name('artists.deactivate');
    Route::post('/artists/{artist}/auto-approval', [ArtistAdminController::class, 'autoApproval'])->middleware('throttle:20,1')->name('artists.auto-approval');
    Route::delete('/artists/{artist}', [ArtistAdminController::class, 'destroy'])->middleware('throttle:10,1')->name('artists.destroy');
    Route::get('/artworks/create', [ArtworkAdminController::class, 'create'])->name('artworks.create');
    Route::post('/artworks', [ArtworkAdminController::class, 'store'])->middleware('throttle:20,1')->name('artworks.store');
    Route::get('/artworks/import', [ArtworkTransferAdminController::class, 'create'])->name('artworks.import');
    Route::post('/artworks/import', [ArtworkTransferAdminController::class, 'store'])->middleware('throttle:5,10')->name('artworks.import.store');
    Route::get('/artworks/export', [ArtworkTransferAdminController::class, 'export'])->middleware('throttle:10,1')->name('artworks.export');
    Route::get('/artworks/{artwork}/edit', [ArtworkAdminController::class, 'edit'])->name('artworks.edit');
    Route::put('/artworks/{artwork}', [ArtworkAdminController::class, 'update'])->middleware('throttle:20,1')->name('artworks.update');
    Route::delete('/artworks/{artwork}', [AdminWorkspaceController::class, 'destroyArtwork'])
        ->middleware('throttle:10,1')
        ->name('artworks.destroy');
    Route::get('/agreements', [AgreementTemplateAdminController::class, 'index'])->name('agreements.index');
    Route::post('/agreements', [AgreementTemplateAdminController::class, 'store'])->middleware('throttle:10,1')->name('agreements.store');
    Route::post('/agreements/{template}/default', [AgreementTemplateAdminController::class, 'makeDefault'])->middleware('throttle:20,1')->name('agreements.default');
    Route::post('/agreements/{template}/active', [AgreementTemplateAdminController::class, 'setActive'])->middleware('throttle:20,1')->name('agreements.active');
    Route::get('/communications', [CommunicationAdminController::class, 'index'])->name('communications.index');
    Route::post('/communications/templates', [CommunicationAdminController::class, 'template'])->middleware('throttle:30,1')->name('communications.templates');
    Route::post('/communications/deliveries/{delivery}/retry', [CommunicationAdminController::class, 'retry'])->middleware('throttle:20,1')->name('communications.retry');
});
Route::get('/security', [SecurityAdminController::class, 'index'])->middleware('permission:exhibitions.security')->name('security.index');
Route::middleware('permission:exhibitions.reports')->group(function (): void {
    Route::get('/reports/{section?}', [ReportAdminController::class, 'index'])->whereIn('section', ['artists', 'artworks', 'financial', 'operations'])->name('reports.index');
    Route::get('/reports/{section}/export', [ReportAdminController::class, 'export'])->whereIn('section', ['artists', 'artworks', 'financial', 'operations'])->middleware('throttle:20,1')->name('reports.export');
});
Route::middleware('permission:exhibitions.review')->group(function (): void {
    Route::get('/applications', [ApplicationAdminController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [ApplicationAdminController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/transition', [ApplicationAdminController::class, 'transition'])->middleware('throttle:30,1')->name('applications.transition');
    Route::get('/applications/{application}/samples/{sample}/preview', [ApplicationAdminController::class, 'previewSample'])->name('applications.samples.preview');
    Route::get('/applications/{application}/samples/{sample}', [ApplicationAdminController::class, 'downloadSample'])->name('applications.samples.download');
    Route::get('/applications/{application}/supporting-documents/{document}', [ApplicationAdminController::class, 'downloadSupportingDocument'])->name('applications.supporting-documents.download');
    Route::get('/applications/{application}/verification', [VerificationAdminController::class, 'show'])->name('applications.verification.show');
    Route::post('/applications/{application}/verification/decision', [VerificationAdminController::class, 'decide'])->middleware('throttle:30,1')->name('applications.verification.decision');
    Route::post('/applications/{application}/verification/payout/verify', [VerificationAdminController::class, 'verifyPayout'])->name('applications.verification.payout.verify');
    Route::post('/applications/{application}/account-state', [VerificationAdminController::class, 'changeAccountState'])->name('applications.account-state');
    Route::get('/applications/{application}/verification/documents/{document}', [VerificationAdminController::class, 'downloadDocument'])->name('applications.verification.documents.download');
    Route::get('/artworks/authenticity', [ArtworkAuthenticityAdminController::class, 'index'])->name('artworks.authenticity.index');
    Route::get('/artworks/{artwork}/authenticity', [ArtworkAuthenticityAdminController::class, 'show'])->name('artworks.authenticity.show');
    Route::post('/artworks/{artwork}/authenticity/decision', [ArtworkAuthenticityAdminController::class, 'decide'])->middleware('throttle:30,1')->name('artworks.authenticity.decide');
    Route::get('/artworks/{artwork}/authenticity/evidence/{evidence}', [ArtworkAuthenticityAdminController::class, 'download'])->name('artworks.authenticity.evidence.download');
    Route::get('/artworks/pricing', [ArtworkPricingAdminController::class, 'index'])->name('artworks.pricing.index');
    Route::get('/artworks/{artwork}/pricing', [ArtworkPricingAdminController::class, 'show'])->name('artworks.pricing.show');
    Route::post('/artworks/{artwork}/pricing/decision', [ArtworkPricingAdminController::class, 'decide'])->middleware('throttle:30,1')->name('artworks.pricing.decide');
    Route::get('/artworks/{artwork}/pricing/evidence/{evidence}', [ArtworkPricingAdminController::class, 'download'])->name('artworks.pricing.evidence.download');
    Route::get('/artworks/review', [ArtworkReviewAdminController::class, 'index'])->name('artworks.review.index');
    Route::get('/artworks/{artwork}/view', [AdminWorkspaceController::class, 'viewArtwork'])->name('artworks.view');
    Route::get('/artworks/{artwork}/review', [ArtworkReviewAdminController::class, 'show'])->name('artworks.review.show');
    Route::get('/artworks/{artwork}/review/media/{media}/display', [ArtworkReviewAdminController::class, 'displayMedia'])->name('artworks.review.media.display');
    Route::get('/artworks/{artwork}/review/media/{media}/download', [ArtworkReviewAdminController::class, 'downloadMedia'])->name('artworks.review.media.download');
    Route::post('/artworks/{artwork}/review/open', [ArtworkReviewAdminController::class, 'open'])->name('artworks.review.open');
    Route::post('/artworks/{artwork}/review/{round}/decision', [ArtworkReviewAdminController::class, 'decideOverall'])->middleware('throttle:30,1')->name('artworks.review.decide-overall');
    Route::post('/artworks/{artwork}/review/{round}/{section}/decision', [ArtworkReviewAdminController::class, 'decide'])->middleware('throttle:30,1')->name('artworks.review.decide');
    Route::post('/artworks/{artwork}/review/{round}/{section}/comments', [ArtworkReviewAdminController::class, 'comment'])->name('artworks.review.comment');
});
Route::middleware('permission:exhibitions.operations')->group(function (): void {
    Route::get('/inventory', [ArtworkInventoryAdminController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/expire', [ArtworkInventoryAdminController::class, 'expire'])->name('inventory.expire');
    Route::get('/inventory/{artwork}', [ArtworkInventoryAdminController::class, 'show'])->name('inventory.show');
    Route::post('/inventory/{artwork}/initialize', [ArtworkInventoryAdminController::class, 'initialize'])->name('inventory.initialize');
    Route::post('/inventory/items/{item}/override', [ArtworkInventoryAdminController::class, 'override'])->name('inventory.override');
    Route::get('/fulfillment', [FulfillmentAdminController::class, 'index'])->name('fulfillment.index');
    Route::get('/fulfillment/{fulfillment}', [FulfillmentAdminController::class, 'show'])->name('fulfillment.show');
    Route::post('/fulfillment/{fulfillment}/transition', [FulfillmentAdminController::class, 'transition'])->name('fulfillment.transition');
    Route::post('/fulfillment/{fulfillment}/pickup', [FulfillmentAdminController::class, 'pickup'])->name('fulfillment.pickup');
    Route::post('/fulfillment/{fulfillment}/evidence', [FulfillmentAdminController::class, 'evidence'])->middleware('throttle:10,1')->name('fulfillment.evidence');
});
Route::middleware('permission:exhibitions.manage')->group(function (): void {
    Route::get('/artworks/publication', [ArtworkPublicationAdminController::class, 'index'])->name('artworks.publication.index');
    Route::get('/artworks/{artwork}/publication', [ArtworkPublicationAdminController::class, 'show'])->name('artworks.publication.show');
    Route::post('/artworks/{artwork}/publication/publish', [ArtworkPublicationAdminController::class, 'publish'])->name('artworks.publication.publish');
    Route::post('/artworks/{artwork}/publication/reconcile', [ArtworkPublicationAdminController::class, 'reconcile'])->name('artworks.publication.reconcile');
    Route::post('/artworks/{artwork}/publication/withdraw', [ArtworkPublicationAdminController::class, 'withdraw'])->name('artworks.publication.withdraw');
});
Route::middleware('permission:exhibitions.finance')->group(function (): void {
    Route::get('/artists/{application}/financial-terms', [ArtistCommissionTermsAdminController::class, 'show'])->name('artists.financial-terms.show');
    Route::post('/artists/{application}/financial-terms', [ArtistCommissionTermsAdminController::class, 'store'])->middleware('throttle:20,1')->name('artists.financial-terms.store');
    Route::get('/sales', [SettlementAdminController::class, 'sales'])->name('sales.index');
    Route::get('/settlements', [SettlementAdminController::class, 'index'])->name('settlements.index');
    Route::get('/settlements/{settlement}', [SettlementAdminController::class, 'show'])->name('settlements.show');
    Route::post('/settlements/{settlement}/action', [SettlementAdminController::class, 'action'])->middleware('throttle:20,1')->name('settlements.action');
    Route::post('/settlements/{settlement}/payout-outcome', [SettlementAdminController::class, 'payoutOutcome'])->middleware('throttle:20,1')->name('settlements.payout-outcome');
});
Route::middleware('permission:exhibitions.support')->group(function (): void {
    Route::get('/disputes', [DisputeAdminController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{case}', [DisputeAdminController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{case}/action', [DisputeAdminController::class, 'action'])->name('disputes.action');
    Route::post('/disputes/{case}/evidence', [DisputeAdminController::class, 'evidence'])->middleware('throttle:10,1')->name('disputes.evidence');
    Route::get('/disputes/{case}/evidence/{evidence}', [DisputeAdminController::class, 'download'])->name('disputes.evidence.download');
});
