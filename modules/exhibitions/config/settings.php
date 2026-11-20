<?php

$field = static fn (
    string $group,
    string $label,
    string $type,
    mixed $default,
    string $description = '',
    array $rules = [],
): array => array_merge(compact('group', 'label', 'type', 'default', 'description'), $rules);

$groups = [
    'general' => ['label' => 'General availability', 'description' => 'Choose which public Exhibitions features are available and configure currency and time zone behavior.'],
    'artist_applications' => ['label' => 'Artist applications', 'description' => 'Control who can apply, what applicants must provide, and the allowed file limits.'],
    'artwork_submission' => ['label' => 'Artwork submissions', 'description' => 'Choose what artists must provide when adding an artwork and set image limits.'],
    'review_publishing' => ['label' => 'Review and publishing', 'description' => 'Choose the approvals required before an artwork can become public and available for sale.'],
    'commission_earnings' => ['label' => 'Commission & Earnings', 'description' => 'Set the platform commission retained from each artwork price after discounts. Shipping and taxes are excluded.'],
    'store_integration' => ['label' => 'Store connection', 'description' => 'Control how approved artworks are sent to Store. Checkout, orders, payment, and shipping are still managed in Store.'],
    'media_authenticity' => ['label' => 'Images and authenticity', 'description' => 'Set image limits and decide which ownership and authenticity documents are required.'],
    'notifications' => ['label' => 'Notifications', 'description' => 'Choose which events send notifications and who receives them.'],
    'lifecycle_data' => ['label' => 'Data retention', 'description' => 'Choose which Exhibitions records remain stored and for how long.'],
];

$definitions = [
    'exhibitions.display_name' => $field('general', 'Exhibitions name', 'string', 'Exhibitions', 'The name shown for this module in public and administration areas.'),
    'exhibitions.enabled' => $field('general', 'Show Exhibitions on the website', 'boolean', true, 'Turn off to hide the public Exhibitions experience without deleting its data.'),
    'exhibitions.artist_applications.enabled' => $field('general', 'Accept artist applications', 'boolean', true, 'Allow new artists to apply through the public application form.'),
    'exhibitions.artwork_submission.enabled' => $field('general', 'Accept new artwork submissions', 'boolean', true, 'Allow approved artists to add new artworks from their dashboard.'),
    'exhibitions.public_artwork_pages.enabled' => $field('general', 'Show public artwork pages', 'boolean', true, 'Allow published artworks to have public detail and purchase pages.'),
    'exhibitions.virtual_exhibitions.enabled' => $field('general', 'Enable virtual exhibitions', 'boolean', true, 'Allow artworks to appear in virtual exhibitions, including works that are not for sale.'),
    'exhibitions.currency.default' => $field('general', 'Default currency', 'string', 'JOD', 'Used for new artwork prices. Existing orders and payout records keep their original currency.', ['options' => ['JOD', 'USD', 'EUR', 'GBP'], 'option_labels' => ['JOD' => 'JOD — Jordanian dinar', 'USD' => 'USD — US dollar', 'EUR' => 'EUR — Euro', 'GBP' => 'GBP — British pound'], 'currency_guard' => true]),
    'exhibitions.timezone.default' => $field('general', 'Default time zone', 'string', 'Asia/Amman', 'Controls how dates and times are displayed.', ['options' => ['Asia/Amman', 'UTC', 'Europe/London', 'America/New_York'], 'option_labels' => ['Asia/Amman' => 'Amman', 'UTC' => 'UTC', 'Europe/London' => 'London', 'America/New_York' => 'New York']]),

    'exhibitions.artist_applications.allow_new' => $field('artist_applications', 'Allow new artist applications', 'boolean', true),
    'exhibitions.artist_applications.allow_drafts' => $field('artist_applications', 'Allow saving applications as draft', 'boolean', true),
    'exhibitions.artist_applications.max_active_per_user' => $field('artist_applications', 'Maximum active applications per user', 'integer', 1, '', ['min' => 1, 'max' => 20]),
    'exhibitions.artist_applications.require_profile_completion' => $field('artist_applications', 'Require profile completion', 'boolean', true),
    'exhibitions.artist_applications.require_identity_documents' => $field('artist_applications', 'Require identity documents', 'boolean', true),
    'exhibitions.artist_applications.require_portfolio_samples' => $field('artist_applications', 'Require portfolio samples', 'boolean', true),
    'exhibitions.artist_applications.require_terms_acceptance' => $field('artist_applications', 'Require terms acceptance', 'boolean', true),
    'exhibitions.artist_applications.require_ownership_declaration' => $field('artist_applications', 'Require ownership declaration', 'boolean', true),
    'exhibitions.artist_applications.allow_resubmission_after_rejection' => $field('artist_applications', 'Allow resubmission after rejection', 'boolean', true),
    'exhibitions.artist_applications.portfolio_images_min' => $field('artist_applications', 'Minimum portfolio images', 'integer', 1, '', ['min' => 0, 'max' => 50]),
    'exhibitions.artist_applications.portfolio_images_max' => $field('artist_applications', 'Maximum portfolio images', 'integer', 8, '', ['min' => 1, 'max' => 50]),
    'exhibitions.artist_applications.document_size_kb_max' => $field('artist_applications', 'Maximum document size (KB)', 'integer', 10240, '', ['min' => 128, 'max' => 102400]),
    'exhibitions.artist_applications.allowed_file_types' => $field('artist_applications', 'Allowed file types', 'json', ['pdf', 'jpg', 'jpeg', 'png'], 'Comma-separated values are accepted.'),

    'exhibitions.artwork_submission.allow_create' => $field('artwork_submission', 'Allow artists to create artworks', 'boolean', true),
    'exhibitions.artwork_submission.allow_drafts' => $field('artwork_submission', 'Allow artists to save drafts', 'boolean', true),
    'exhibitions.artwork_submission.require_active_artist' => $field('artwork_submission', 'Require active artist status', 'boolean', true),
    'exhibitions.artwork_submission.images_max' => $field('artwork_submission', 'Maximum images per artwork', 'integer', 12, '', ['min' => 1, 'max' => 100]),
    'exhibitions.artwork_submission.image_size_kb_max' => $field('artwork_submission', 'Maximum image size (KB)', 'integer', 20480, '', ['min' => 128, 'max' => 204800]),
    'exhibitions.artwork_submission.allowed_image_formats' => $field('artwork_submission', 'Allowed image formats', 'json', ['jpg', 'jpeg', 'png', 'webp']),
    'exhibitions.artwork_submission.require_main_image' => $field('artwork_submission', 'Require main image', 'boolean', true),
    'exhibitions.artwork_submission.require_signature_image' => $field('artwork_submission', 'Require signature image', 'boolean', false),
    'exhibitions.artwork_submission.require_back_image' => $field('artwork_submission', 'Require back image', 'boolean', false),
    'exhibitions.artwork_submission.require_ownership_evidence' => $field('artwork_submission', 'Require proof of ownership', 'boolean', true, 'The artist must provide evidence that they own or created the artwork.'),
    'exhibitions.artwork_submission.require_story' => $field('artwork_submission', 'Require artwork story', 'boolean', true),
    'exhibitions.artwork_submission.require_care_instructions' => $field('artwork_submission', 'Require care instructions', 'boolean', true),
    'exhibitions.artwork_submission.require_dimensions' => $field('artwork_submission', 'Require dimensions', 'boolean', true),
    'exhibitions.artwork_submission.require_weight' => $field('artwork_submission', 'Require weight', 'boolean', true),
    'exhibitions.artwork_submission.require_preparation_lead_time' => $field('artwork_submission', 'Require preparation lead time', 'boolean', true),
    'exhibitions.artwork_submission.original_quantity' => $field('artwork_submission', 'Original artwork quantity', 'integer', 1, 'Enforced by the server.', ['min' => 1, 'max' => 1, 'locked' => true]),
    'exhibitions.artwork_submission.original_backorders_enabled' => $field('artwork_submission', 'Backorders for Original', 'boolean', false, 'Original artworks cannot be backordered.', ['locked' => true]),
    'exhibitions.artwork_submission.original_edition_fields_required' => $field('artwork_submission', 'Require edition fields for Original', 'boolean', false, '', ['locked' => true]),

    'exhibitions.review.manual_required' => $field('review_publishing', 'Require administration review', 'boolean', true, 'An administrator must review the artwork before it can be published.'),
    'exhibitions.review.require_content' => $field('review_publishing', 'Require content approval', 'boolean', true),
    'exhibitions.review.require_images' => $field('review_publishing', 'Require image approval', 'boolean', true),
    'exhibitions.review.require_authenticity' => $field('review_publishing', 'Verify ownership and authenticity', 'boolean', true, 'Ownership information and supporting documents must be approved.'),
    'exhibitions.review.require_pricing' => $field('review_publishing', 'Approve price before publishing', 'boolean', true, 'The platform must approve the sale price before the artwork becomes available.'),
    'exhibitions.review.require_all_sections' => $field('review_publishing', 'Require all review sections before final approval', 'boolean', true),
    'exhibitions.review.publish_automatically' => $field('review_publishing', 'Publish automatically after approval', 'boolean', false),
    'exhibitions.review.separate_publication_required' => $field('review_publishing', 'Require separate commercial publication step', 'boolean', true),
    'exhibitions.review.safe_republish' => $field('review_publishing', 'Allow safe republish', 'boolean', true),
    'exhibitions.review.allow_changes_after_publication' => $field('review_publishing', 'Allow changes after publication', 'boolean', false),
    'exhibitions.review.require_review_after_published_edit' => $field('review_publishing', 'Require review after published artwork edit', 'boolean', true),
    'exhibitions.review.workflow_states' => $field('review_publishing', 'Workflow states', 'json', ['draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'published', 'reserved', 'sold', 'rejected', 'archived'], 'Core workflow states cannot be removed.', ['locked_items' => ['draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'published', 'reserved', 'sold', 'rejected', 'archived']]),

    'exhibitions.commission.enabled' => $field('commission_earnings', 'Enable platform commission', 'boolean', true, '', ['hidden' => true, 'locked' => true]),
    'exhibitions.commission.default_rate' => $field('commission_earnings', 'Default Platform Commission (%)', 'decimal', '20.000', 'The percentage retained by the platform from the artwork price after discounts. The artist earning is calculated automatically as 100% minus this rate. Existing order-item snapshots never change.', ['min' => '0.000', 'max' => '100.000', 'scale' => 3]),
    'exhibitions.commission.allow_artist_override' => $field('commission_earnings', 'Allow artist-specific commission', 'boolean', true, '', ['hidden' => true, 'locked' => true]),
    'exhibitions.commission.allow_artwork_override' => $field('commission_earnings', 'Allow artwork-specific commission', 'boolean', false, 'Reserved for a future contractual requirement.', ['hidden' => true, 'locked' => true]),
    'exhibitions.commission.minimum_rate' => $field('commission_earnings', 'Minimum allowed commission (%)', 'decimal', '0.000', '', ['min' => '0.000', 'max' => '100.000', 'scale' => 3, 'hidden' => true]),
    'exhibitions.commission.maximum_rate' => $field('commission_earnings', 'Maximum allowed commission (%)', 'decimal', '100.000', '', ['min' => '0.000', 'max' => '100.000', 'scale' => 3, 'hidden' => true]),
    'exhibitions.commission.calculation_base' => $field('commission_earnings', 'Commission Calculation Basis', 'string', 'merchandise_after_discount', 'Commission applies to the artwork price after discounts, excluding shipping and taxes.', ['options' => ['merchandise_after_discount'], 'locked' => true]),
    'exhibitions.commission.discount_responsibility' => $field('commission_earnings', 'Discount responsibility', 'string', 'artist', '', ['options' => ['discounts_not_allowed', 'platform', 'artist', 'shared'], 'hidden' => true]),
    'exhibitions.commission.discounts_allowed' => $field('commission_earnings', 'Allow discounts', 'boolean', true, '', ['hidden' => true]),
    'exhibitions.commission.require_before_publication' => $field('commission_earnings', 'Require commission before publication', 'boolean', true, '', ['hidden' => true, 'locked' => true]),
    'exhibitions.commission.show_to_artist' => $field('commission_earnings', 'Show commission to artist', 'boolean', true, '', ['hidden' => true]),
    'exhibitions.commission.show_estimated_artist_net' => $field('commission_earnings', 'Show estimated artist net', 'boolean', true, '', ['hidden' => true]),
    'exhibitions.settlement.eligibility_trigger' => $field('commission_earnings', 'When can the artist request payment?', 'string', 'delivered_return_window_completed', 'Choose whether the artist waits for the customer return period or can request payment immediately after delivery.', ['options' => ['delivered_return_window_completed', 'delivered'], 'option_labels' => ['delivered_return_window_completed' => 'After the customer return period', 'delivered' => 'Immediately after delivery']]),
    'exhibitions.settlement.return_window_days' => $field('commission_earnings', 'Customer return period (days)', 'integer', 14, 'Number of days after delivery during which the customer may request a return. Artist payment waits until this period ends.', ['min' => 0, 'max' => 365, 'input_help' => 'Recommended: 14 days.']),
    'exhibitions.settlement.payout_approval_required' => $field('commission_earnings', 'Require finance approval before payment', 'boolean', true, 'When on, an administrator must approve the artist payout before it can be scheduled.'),
    'exhibitions.settlement.minimum_payout_minor' => $field('commission_earnings', 'Minimum payout amount', 'integer', 0, 'Internal payout threshold.', ['min' => 0, 'max' => 1000000000, 'hidden' => true]),
    'exhibitions.settlement.allow_manual_hold' => $field('commission_earnings', 'Allow payout hold', 'boolean', true, 'Internal protection for disputes and finance reviews.', ['hidden' => true]),

    'exhibitions.store.enabled' => $field('store_integration', 'Allow artwork sales through Store', 'boolean', true, 'Send approved artworks to Store so customers can purchase them.'),
    'exhibitions.store.owner_code' => $field('store_integration', 'Store owner code', 'string', 'exhibitions', '', ['pattern' => '/^[a-z][a-z0-9._-]{1,63}$/']),
    'exhibitions.store.create_sellable_after_publication' => $field('store_integration', 'Automatically create Store sellable after publication', 'boolean', true),
    'exhibitions.store.require_availability' => $field('store_integration', 'Show Buy only when artwork is available', 'boolean', true, 'Hide the purchase button when Store reports that the artwork is sold, reserved, or unavailable.'),
    'exhibitions.store.hide_purchase_when_disabled' => $field('store_integration', 'Hide purchase button when Store is disabled', 'boolean', true),
    'exhibitions.store.default_visibility' => $field('store_integration', 'Default product visibility', 'string', 'visible', '', ['options' => ['visible', 'hidden']]),
    'exhibitions.store.stock_tracking' => $field('store_integration', 'Default stock tracking', 'boolean', true),
    'exhibitions.store.default_max_quantity' => $field('store_integration', 'Default maximum quantity', 'integer', 1, '', ['min' => 1, 'max' => 100000]),
    'exhibitions.store.original_max_quantity' => $field('store_integration', 'Maximum quantity for Original', 'integer', 1, '', ['min' => 1, 'max' => 1, 'locked' => true]),
    'exhibitions.store.backorders_enabled' => $field('store_integration', 'Default backorder policy', 'boolean', false),
    'exhibitions.store.safe_republish' => $field('store_integration', 'Enable safe republish', 'boolean', true),

    'exhibitions.media.max_images' => $field('media_authenticity', 'Maximum artwork images', 'integer', 12, '', ['min' => 1, 'max' => 100]),
    'exhibitions.media.allowed_formats' => $field('media_authenticity', 'Allowed image formats', 'json', ['jpg', 'jpeg', 'png', 'webp']),
    'exhibitions.media.max_image_size_kb' => $field('media_authenticity', 'Maximum image size (KB)', 'integer', 20480, '', ['min' => 128, 'max' => 204800]),
    'exhibitions.media.generate_public_derivatives' => $field('media_authenticity', 'Generate public display derivatives', 'boolean', true),
    'exhibitions.media.originals_private' => $field('media_authenticity', 'Keep original images private', 'boolean', true),
    'exhibitions.media.strip_metadata' => $field('media_authenticity', 'Strip embedded metadata', 'boolean', true),
    'exhibitions.media.require_authenticity_evidence' => $field('media_authenticity', 'Require authenticity evidence', 'boolean', true),
    'exhibitions.media.require_certificate' => $field('media_authenticity', 'Require certificate of authenticity', 'boolean', true),
    'exhibitions.media.allow_private_evidence' => $field('media_authenticity', 'Allow private supporting evidence', 'boolean', true),
    'exhibitions.media.max_evidence_files' => $field('media_authenticity', 'Maximum evidence files', 'integer', 10, '', ['min' => 1, 'max' => 100]),
    'exhibitions.media.allowed_evidence_formats' => $field('media_authenticity', 'Allowed evidence formats', 'json', ['pdf', 'jpg', 'jpeg', 'png']),
    'exhibitions.media.max_evidence_size_kb' => $field('media_authenticity', 'Maximum evidence size (KB)', 'integer', 20480, '', ['min' => 128, 'max' => 204800]),

    'exhibitions.lifecycle.deactivate_behavior' => $field('lifecycle_data', 'Deactivate behavior', 'string', 'hide_routes_navigation', '', ['options' => ['hide_routes_navigation'], 'locked' => true]),
    'exhibitions.lifecycle.uninstall_behavior' => $field('lifecycle_data', 'Permanent removal behavior', 'string', 'platform_destructive_purge', 'Platform 2.5 permanently removes declared module data and files.', ['options' => ['platform_destructive_purge'], 'locked' => true]),
    'exhibitions.lifecycle.purge_behavior' => $field('lifecycle_data', 'Purge behavior', 'string', 'platform_destructive_purge', '', ['options' => ['platform_destructive_purge'], 'locked' => true]),
    'exhibitions.retention.artist_applications' => $field('lifecycle_data', 'Retain artist applications', 'boolean', true),
    'exhibitions.retention.artworks' => $field('lifecycle_data', 'Retain artworks', 'boolean', true),
    'exhibitions.retention.contracts' => $field('lifecycle_data', 'Retain contracts', 'boolean', true),
    'exhibitions.retention.private_evidence' => $field('lifecycle_data', 'Retain private evidence', 'boolean', true),
    'exhibitions.retention.audit_logs' => $field('lifecycle_data', 'Retain audit logs', 'boolean', true),
    'exhibitions.retention.period_days' => $field('lifecycle_data', 'Retention period (days)', 'integer', 2555, '', ['min' => 1, 'max' => 36500]),
];

$notificationEvents = [
    'application_submitted', 'documents_requested', 'application_approved', 'application_rejected',
    'artwork_submitted', 'changes_requested', 'artwork_approved', 'artwork_published',
    'artwork_reserved', 'artwork_sold', 'order_delivered', 'settlement_eligible',
    'payout_approved', 'payout_paid', 'dispute_opened',
];
$notificationChannels = ['email', 'in_app', 'admin', 'artist'];
foreach ($notificationEvents as $event) {
    foreach ($notificationChannels as $channel) {
        $key = "exhibitions.notifications.$event.$channel";
        $definitions[$key] = $field(
            'notifications',
            ucwords(str_replace('_', ' ', $event)).' — '.ucwords(str_replace('_', ' ', $channel)),
            'boolean',
            true,
        );
    }
}

$notificationCopy = [
    'exhibitions.notifications.application_submitted.admin' => ['New artist application', 'Notify administrators when an artist submits an application.'],
    'exhibitions.notifications.artwork_submitted.admin' => ['New artwork submitted', 'Notify administrators when an artist submits an artwork for review.'],
    'exhibitions.notifications.changes_requested.artist' => ['Artwork changes requested', 'Notify the artist when the reviewer asks for changes or additional information.'],
    'exhibitions.notifications.artwork_sold.artist' => ['Artwork sold', 'Notify the artist when a confirmed sale is recorded.'],
    'exhibitions.notifications.payout_paid.artist' => ['Artist payout updates', 'Notify the artist when the administration sends a payment and when receipt must be confirmed.'],
    'exhibitions.notifications.dispute_opened.admin' => ['New dispute opened', 'Notify administrators when a sale, delivery, or ownership dispute is opened.'],
];
foreach ($notificationCopy as $key => [$label, $description]) {
    $definitions[$key]['label'] = $label;
    $definitions[$key]['description'] = $description;
}

return compact('groups', 'definitions');
