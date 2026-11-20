# Store Changelog

## 12.10.6 - 2026-11-16

- The artist name field is now optional: publishing a normal (non-artwork) product no longer requires it.
- The artist name field is hidden from the admin product create and edit screens, and the product summary no longer shows "Artist not set".
- Artwork products keep their artist attribution through the artwork flow.
## 12.10.3 - 2026-10-15

- Storefront colours now follow the active platform theme tokens (accent, tint, text and muted) instead of a fixed brand palette, keeping the previous values as fallbacks so the plugin looks native on any site.
- The supplied default product templates inherit the same theme tokens.

## 12.10.2 - 2026-09-08

- Add compact public Artworks and Virtual Exhibition shortcuts beside the customer-area order action.
- Keep the shortcut actions accessible and responsive on mobile screens.

## 12.10.1 - 2026-09-05

- Restore direct Store settings discovery from the platform plugin screen.
- Expose Product Templates as a first-level Store administration entry and flatten Store settings navigation to supported menu depth.
- Make Page Builder element registration wait for the editor runtime and remain idempotent across editor initialization.
- Support platform Core 3.4.3 and later within the current major release.

## 12.10.0 - 2026-09-04

- Use the platform language as the only storefront and administration language selector.
- Remove the Store-specific default-language configuration.
- Keep source-language product content visible through deterministic translation fallback when the platform language changes.
- Require platform Core 3.4.4 or newer.

## 12.9.0 - 2026-09-04

- Restored access to Store settings, category management, and product-template routes.
- Separated Store-owned products from artwork products owned by Exhibitions.
- Added storefront sections for products, clothing, and artworks.
- Normalized imported category slugs and preserved explicit category hierarchy.

## 12.8.0 - 2026-09-04

- Added an idempotent Store-owned Page Builder page that appears in the Pages workspace and is linked through the editable frontend Menu Builder.
- Added a standard Store Catalog element with category, tag, brand, price, stock, sale, search, sort, column-count, and page-size controls.
- Limited the managed storefront page to standard simple and variant products while keeping artwork storefronts independent.
- Reused the Store quick-view and asynchronous cart controls so product quantities change without reloading the page.
- Preserved user menu labels and ordering across Store reactivation while hiding the managed page whenever Store is inactive.

## 12.7.1 - 2026-09-04

- Made the complete Store migration history safe to replay during plugin upgrades when the platform discovers a newly owned table.
- Preserved all existing catalog, order, customer, artwork, inventory, and settings data while allowing the product-template table to be created.
- Added an upgrade regression test that removes Store migration records and verifies a full replay without duplicate-table errors or data loss.

## 12.7.0 - 2026-09-04

- Added a Store-owned product template studio with HTML, isolated CSS, separate JavaScript, live preview, and a complete product variable reference.
- Added a protected published default template that can be read and copied but not edited or deleted; copied and custom templates support draft and published states.
- Made the existing Page Builder Products element template-aware while preserving legacy saved elements and the existing Categories and Brands elements.
- Added product source, responsive grid/card/slider/template layout, pagination, visibility, spacing, and image-fit controls around any published product template.
- Kept all template schema, rendering, routes, permissions, assets, and lifecycle ownership inside the Store plugin so the integration disappears when Store is inactive.

## 12.6.0 - 2026-09-04

- Added a Store-owned marketplace catalog with category, tag, brand, price, stock, sale, and featured filters.
- Added three responsive column choices and three validated page-size choices while preserving filters through pagination and sorting.
- Added accessible product quick view and session cart quantity controls with AJAX updates and redirect-based fallbacks.
- Kept the public Store route lifecycle-owned by Menu Builder and retained Store-owned Page Builder catalog elements.

## 12.5.1 - 2026-09-03

- Added a complete UTF-8 CSV catalog export for products, translations, variants, prices, inventory, categories, attributes, artwork data, and product media metadata.
- Added an idempotent CSV catalog import with per-product transactions, explicit existing-product behavior, validation summaries, and approved Store media storage.
- Added guarded remote image retrieval with redirect, size, MIME, dimension, and public-network validation so imported product images never remain dependent on the source site.

## 12.5.0 - 2026-08-31

- Complete the Store-owned artwork product workflow for administrators and artist sellers, including consistent four-step entry, validation, media, authenticity, provenance, draft, review, publication, duplication, editing, archiving, and deletion behavior.
- Add direct administrator publication and editing while artist changes return through Store review, with reservation, active-order, and sale safeguards.
- Support administrator-selected artists and platform-owned artworks without an artist account while keeping all sellable product, checkout, inventory, and public artwork detail behavior inside Store.
- Serve artwork products through the dedicated artwork storefront template, include all public artwork fields, retain private evidence protection, and keep generic products on the generic product template.
- Improve seller navigation integration, action menus, return paths, media persistence on Windows-backed storage, and local user experience without moving Store ownership to Exhibitions.

## 12.4.0 - 2026-08-30

- Restore the complete four-step artwork entry workflow as a Store-owned product flow for artists and administrators.
- Add resumable autosave, optimistic draft conflict protection, private originals, safe display derivatives, specialized image roles, ordering, replacement, and immutable media history.
- Add Store-owned authenticity declarations, private supporting evidence, provenance history, review records, and framing and packaging costs.
- Require artists to submit complete artwork records for Store review; administrators meet the same completeness and evidence gates but publish directly without a second approval.
- Keep schema, routes, storage, publication, review, and lifecycle ownership entirely inside Store.

## 12.3.0 - 2026-08-30

- Add Artwork as a native Store product type with one sellable variant and Store-owned price, inventory, media, seller ownership, and review state.
- Add a dedicated multi-step artwork form for administrators and approved artist sellers; generic product mutation paths cannot bypass artwork review.
- Add seller duplication, withdraw-for-edit, and archive actions with reservation and order safeguards.
- Add an approval gate that prevents artwork publication until Store review succeeds.

## 12.2.4 — 2026-08-30

- Require a central platform account before checkout while preserving the guest cart through sign-in or account creation.
- Prevent duplicate single-quantity artwork additions and improve cart-state feedback without changing platform core.
- Harden shipping quote recalculation against duplicate requests and rate-limit failures.
- Package Store-account navigation, canonical user identity reads, and order-total display corrections completed during final platform testing.

## 12.2.3 — 2026-08-20

- Added module-owned Page Builder elements for public products, categories, and brands.
- Added responsive columns, ordering, visibility, spacing, and image-fit controls without changing Page Builder core files.

## 12.2.2 — 2026-08-10

- Load the Store frontend-menu service from the lifecycle entry point so dependency injection works before an inactive plugin provider is registered.

## 12.2.1 — 2026-08-10

- Updated the plugin manifest to the current platform ownership contract.
- Replaced the legacy executable uninstall script with declarative table, storage, record, column, setting, and operation ownership.
- Moved provider route access to the application router so routes remain owned exclusively by the manifest catalog.
- Registered every shipped Store JavaScript asset in the managed asset catalog.

## 12.2.0 — 2026-08-02

Stable external-sellable lifecycle contract release.

### Added

- Add the public `ExternalSellableLifecycleFeed` v1 contract, its immutable record DTO, and the after-commit `ExternalSellableLifecycleChanged` event.
- Persist owner-scoped `reserved`, `released`, `committed`, and `returned` records in a cursor-based durable feed with unique idempotency keys.
- Include order public ID and human order number, order-item public ID, authenticated customer user ID when present, and the current line/payment snapshot without customer PII.
- Support fail-closed ownership checks by either order public ID or order number, including pre-12.2 external orders.

### Changed

- Emit lifecycle records from successful checkout, cancellation, delivery, return, and a separate transition to paid within the same Store transaction as the source update.
- Publish lifecycle notifications only after the Store transaction commits; consumers can recover missed or failed notifications by reading the durable feed.
- Declare `store.integrations.external-sellable-lifecycle.v1` and the public lifecycle event in the module manifest.

### Database

- Add `store_external_sellable_lifecycle_events` with owner/sequence and order-reference indexes plus a globally unique idempotency key.
- Add all external-sellable contract tables to Store's explicit purge ownership list.

### Tests

- Cover cursor sequencing, financial snapshots, payment refreshes, cancellation, delivery, return, retry idempotency, ownership isolation, guest fail-closed behavior, and consumer-failure recovery.
- Resolve Store administration performance controllers through the container so the existing query-count regression checks use the current constructor contract.

## 12.0.1 — 2026-07-24

Stable hardening release.

### Fixed

- Revalidate a coupon immediately before order creation and require the customer to review totals when an open cart coupon becomes invalid or paused.
- Roll back orders, reservations, and inventory atomically when checkout fails after stock reservation.
- Ignore client-supplied prices, discounts, totals, status, tax, and shipping amounts during checkout.
- Reject disabled products, unavailable variants, and stale catalog prices during checkout.
- Paginate the administration inventory workspace and calculate its totals in SQL.
- Remove N+1 queries from product variants, attributes, options, prices, and inventory administration views.
- Bound interactive report rows and aggregate report metrics in SQL.
- Stream complete order and inventory CSV exports instead of materializing every row in memory.
- Add throttling to checkout confirmation, coupon application, shipping quotes, customer cancellation/reorder actions, address writes, and order notification actions.
- Ensure Store module deployment ownership supports lifecycle archiving during non-destructive uninstall and purge.
- Fix Blade compilation of nested order-print permissions so administrative order details render reliably in production.

### Tests

- Added direct-route authorization and IDOR coverage for guests, customers, Store viewers, Store managers, platform administrators, and users without Store access.
- Added upload validation, mass-assignment, stored-XSS, price-tampering, stale cart, paused coupon, checkout rollback, and rate-limit regression coverage.
- Added bounded-query and pagination tests for large reports, inventory, and attribute administration.
- Added an authenticated administrative order-detail rendering regression test.
- Validated MariaDB promotion-target constraints and real concurrent reservation behavior.
- Executed isolated lifecycle and destructive restore gates without touching production data.

### Database

- No new migration is required.
- The release is backward-compatible with the 12.0.0 Store schema.
