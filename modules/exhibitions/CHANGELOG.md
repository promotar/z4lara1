# Exhibitions Changelog

## 1.10.2 - 2026-11-19

- Artist registration: text/select/checkbox values are auto-saved in the browser and restored after a reload, so nothing typed is lost.
- Step bar marks the steps that contain validation errors.
- Identity documents are validated client-side (type/size) before submit to avoid losing the form to a file error.

## 1.10.1 - 2026-11-19

- The page heading now lives in the content column only, never under the rail; the rail starts level with the heading instead of covering the title.


## 1.9.9 - 2026-11-19

- Artist workspace rebuilt on one deterministic layout: the rail is out of flow and the grid reserves its column, so the sidebar can no longer stretch a row (the empty band under the quick action), cover the page header, or sit on top of the mobile menu.
- Sidebar palette (background, text, active item) is read from the admin theme settings; the active label picks white or dark by measured contrast.
- Removed the legacy fixed-rail compensations, planted colours and the forced page direction from the module stylesheet.


## 1.9.4 - 2026-11-19

- Removed the duplicate language switcher from the artist dashboard; the site header owns language switching.
- Plugin pages no longer load the page-builder demo stylesheet, removing conflicting spacing on artist screens.


## 1.9.3 - 2026-11-19

- The selling readiness card is hidden once the artist completes 100% of the requirements; it only appears while something is still missing.


## 1.9.2 - 2026-11-19

- Artist workspace layout rebuilt on a CSS grid: the sidebar is a sticky grid column instead of a fixed overlay over the page/header.
- Sidebar palette is read from the admin theme settings (sidebar background/text, active menu color) instead of hard-coded colors.


## 1.9.1 - 2026-11-19

- Artist workspace pages now use the public site header/footer layout instead of the admin shell.
- Sidebar navigation uses real icons (not letters) with RTL-aware alignment; the sidebar offset follows the real header height.


## 1.8.8 - 2026-11-19

- Language switching inside the artist dashboard now also updates the page (site) locale, so the dashboard content and its `dir` attribute actually change; previously only the account preference was stored and the route is not a core panel path.
- Added RTL layout rules for the exhibitions stylesheet: table alignment, timeline markers, accent borders, filter chips, form fields and dashboard header now mirror correctly in Arabic.

## 1.8.7 - 2026-11-19

- The artist dashboard now has a working language switcher. It stores the choice on the signed-in artist account, because the dashboard follows the account language rather than the public site language.
- Added `ui.artist_language` and `ui.artist_language_apply` translation keys (EN/AR).

## 1.8.6 - 2026-11-16

- Arabic label fixes: the completion year field reads "سنة الإنجاز" instead of the academic-year wording, and the edition label is translated instead of showing a placeholder.
- Artwork uploads accept images from 400px per dimension instead of 800px, and the media help text reads the limits from configuration.
- Upload validation messages now report the configured limits together with the actual rejection reason.
- The submission banner reflects automatic approval: artists with auto-approval no longer see "submitted for manual review" after publishing.
## 1.8.3 - 2026-10-15

- Align the three artwork card actions on one baseline by neutralising the wrapper forms of the Duplicate and Delete buttons.

## 1.8.2 - 2026-10-15

- Normalise the artwork card action buttons to one fixed 44px height with identical spacing, so theme rules can no longer stretch the View work link relative to the buttons.

## 1.8.1 - 2026-10-15

- Artwork cards in the artist list now keep the artwork information on top and show the three actions (View work, Duplicate, Delete) side by side on their own row inside the same card.

## 1.8.0 - 2026-10-15

- Artists can delete their own artworks from the artworks list and from the artwork page, with a confirmation step; deleting a published artwork first withdraws it from the Store.
- Deletion is refused when the artwork has sale or order history, a dispute, reserved or sold inventory, or an active virtual exhibition placement.
- Artwork image files are removed from storage together with the record, and the artworks list now shows success and error messages.

## 1.7.9 - 2026-10-15

- Unify the artwork row actions (View work and Duplicate): matching height, font size, icon size, radius and hover state so both buttons read as one family.

## 1.7.8 - 2026-10-15

- Fix the Back to My Account link colours so the label stays readable on hover and focus, overriding theme link styles.

## 1.7.7 - 2026-10-15

- Artists can add a new artwork category: choosing the new category option reveals a free-text field, and the typed value is stored as the artwork category.
- The artwork form submit button now reflects the artist approval mode: artists with automatic approval see Publish artwork, everyone else keeps Submit for manual review, with a short explanation.

## 1.7.6 - 2026-10-15

- Fix artwork submission failing with a sold buyer name validation error when the artwork is not marked as sold.

## 1.7.5 - 2026-10-15

- Fix a blocking form issue: the edition quantity field is only required while a limited edition is selected, so artworks without an edition can be submitted again.
- Add a Back to My Account shortcut in the artist workspace header.

## 1.7.4 - 2026-10-15

- Move the artist row actions to their own full-width line under each artist, so the buttons no longer overlap the record columns.

## 1.7.3 - 2026-10-15

- Lay the artist row actions out on a single line so they sit side by side instead of stacking inside the narrow actions column.

## 1.7.2 - 2026-10-15

- Tidy the automatic approval control in the artists list: shorter labels that stay on one line instead of wrapping, with a tooltip explaining the current state.

## 1.7.1 - 2026-10-15

- The artist workspace sidebar now uses the admin sidebar brand colour instead of the accent colour, so it matches the platform identity.

## 1.7.0 - 2026-10-15

- Add administrator-granted automatic artwork approval: from the artists workspace an administrator can grant or remove automatic approval per artist. New artworks from an approved artist skip the manual review queue and are approved (and synchronized for publication) automatically.
- Manual review stays the default for every artist; the grant is explicit per artist and recorded with who granted it and when.
- Content approval no longer requires physical dimensions, so artworks created through the simplified artist form can be approved.

## 1.6.9 - 2026-10-15

- Public exhibition pages, the 3D viewer, the artist workspace and the administration screens now take their colours from the active platform theme instead of a fixed brand palette, keeping the previous values as fallbacks.

## 1.6.8 - 2026-10-14

- Add a simplified single-page artwork form for artists: image upload (multiple files), name, price, optional short description, year, category, edition size, orientation, condition and availability, with the remaining metadata filled in automatically.
- Allow several artwork images per submission; the first becomes the primary image and the rest are stored as detail views.
- Replace the ownership checklist with a single plain-language pledge covering authorship, delivery to the platform when the artwork sells, and keeping the artwork status up to date.
- Add availability states: available for sale, sold outside the platform (buyer name recorded, listing kept but no longer purchasable) and unavailable (withdrawn from Store permanently).
- Store the offline buyer name with the artwork and make public artwork pages dynamic: hide fields the artist no longer supplies, render tabs only when they have content, and remove the Authenticity tab.
- Simplify the public artwork layout to a single column with compact inline specifications.

## 1.6.7 - 2026-09-12

- Reject 3D placement when an artwork has no valid physical width and height instead of silently rendering it at an invented one-centimetre size.
- Identify each affected artwork in the artist Virtual Exhibition workspace and keep unknown-size neighbouring placements from producing unsafe wall-clearance calculations.
- Handle placement rate limits with a visible retry countdown while preserving the server-side throttle and the current reservation.
- Restore the intended placement movement control and surface artwork preview-loading failures instead of hiding them.

## 1.6.6 - 2026-09-08

- Convert the existing-account artist application into an accessible four-step wizard with draft preservation and review summary.
- Move applications from users whose platform email is already verified directly into review.
- Improve the artwork ownership declaration layout and expose public artwork and virtual exhibition shortcuts to the customer area.

## 1.6.5

- Follow the platform language and writing direction across public exhibition pages and notification delivery.
- Remove the Exhibitions-specific default-language setting while preserving localized notification templates.
- Require platform Core 3.4.4 or newer.

## 1.6.4

- Centralized the module namespace loader so lifecycle callbacks can resolve every module-owned dependency before the provider boots.
- Added regression coverage for the placement geometry dependency that previously stopped non-destructive updates.

## 1.6.3

- Made the module lifecycle load its placement-layout dependency independently of the active provider so fresh installs and non-destructive updates can resolve it before module autoload registration.
- Added an isolated regression test for the plugin lifecycle loading boundary.

## 1.6.2

- Replaced fixed artwork orientation and 60–100% slot-size restrictions with real wall-space evaluation.
- Enforced a 20 cm minimum gap between neighbouring artworks and safe clearance from wall edges during preview, hold, confirmation, and movement.
- Added non-destructive placement-geometry normalization for the five existing luxury halls while preserving stable hall, room, and slot identifiers.
- Updated the artist 3D placement interface to show the actual safe space available for each artwork and rebuilt the production viewer bundle.

## 1.6.1

- Integrated artwork ownership, right-to-sell disclosures, optional private evidence, and provenance entry as the fifth artwork-submission wizard step.
- Removed the ambiguous supporting-evidence handoff from pricing while preserving the existing private authenticity routes and review history.
- Kept artwork autosave scoped to artwork fields so private evidence files are uploaded only on an explicit draft save or review submission.

## 1.6.0

- Added administration workflows for assigning an existing platform user as an artist, suspending or removing artist access, and safely moving eligible artworks back to private drafts.
- Added administrator-owned and artist-assigned artwork creation and editing while preserving the existing review, media, Store publication, and audit pipelines.
- Added UTF-8 CSV artwork export and idempotent draft import with existing-user email matching, protected-record safeguards, and safe remote image ingestion.

## 1.5.28

- Aligned the module manifest, platform compatibility contract, lifecycle tests, and optional Store integration with Core 3.4.3.
- Preserved the Core account destination after authentication while keeping Exhibitions-owned application review transitions and Artist Studio account sections.
- Retired the unused VE-03 technical-loader spike, its private bundle, routes, and orphaned data adapter; the unified GLB pipeline remains the only VE-03 runtime.
- Normalized and validated exhibition checkout return state inside the Exhibitions bridge without restoring obsolete Store UI contracts.
- Updated the legacy test harness to exercise plugin-owned authorization directly while Core authorization remains independently covered.

## 1.5.27 - 2026-08-24

- Keep authored hall geometry visible on every automatic quality tier so distant rooms and doorways no longer expose a black scene background while the visitor approaches.
- Improve mobile movement with a circular joystick boundary, a small dead zone, progressive response, proportional walking speed, and smoother visual feedback.
- Move the mobile room and quality panel to a compact lower-right position opposite the movement joystick in portrait and landscape layouts.
- Keep the update isolated to Exhibitions; no platform core, LMS, Blog, Store, or other plugin files are modified.

## 1.5.26 - 2026-08-20

- Added module-owned Page Builder elements for public artworks and virtual exhibitions.
- Added responsive layout, ordering, visibility, spacing, and image-fit controls while preserving existing saved artwork elements.
- Kept the update isolated to Exhibitions; no Page Builder core files or runtime exhibition data are modified.

## 1.5.25 - 2026-08-18

- Finalized the five independent luxury 3D halls, corrected display-slot distribution, lighting, collision floors, camera controls, and visitor/artist preview behavior.
- Added non-destructive hall creation, preview, editing, duplication, deletion, multi-hall publication, and the guided GLB/processed-build import workflow.
- Added artist-owned virtual placement navigation, hall-capacity details, placement filters, multi-hall artwork display, administration removal history, and sold-state propagation across every hall.
- Automatically synchronize approved artworks with Store publication, retain sold artworks with a Sold state, and keep Store-only features hidden when Store is unavailable.
- Kept visitors inside the exhibition after adding an artwork to the cart and added an in-exhibition order summary and checkout handoff without pausing movement after the dialog closes.
- Added artwork duplication, improved artist-workspace layouts, filters, status presentation, and the module-owned Virtual Exhibition sidebar navigation.
- Consolidated artist account maintenance into Profile & Documents: account name and email, public profile, contact details, biography, professional history, portfolio/social links, password, verified documents, agreement, commission, and payout setup.
- Removed Account Security and Profile & Security navigation, kept the platform-header artist link inside the module workspace, and require renewed verification after a login-email change.
- Added idempotent repair for legacy zero-commission snapshots and delivered Store orders while preserving financial history and using Commission & Earnings as the canonical commission source.
- Package remains a non-destructive module update: it contains no core or Store files and does not purge agreements, halls, artworks, placements, settlements, or uploaded runtime data.

## 1.5.24 - 2026-08-17

- Grant the core's exact route permissions for every approved-artist virtual-exhibition endpoint during module install, activation, and reactivation.
- Restore the Virtual Exhibition artist-sidebar entry and eliminate HTTP 403 responses after a non-destructive module update.
- Keep the repair module-owned; no core, Store, agreement, hall, artwork, or other runtime data is modified.

## 1.5.23 - 2026-08-17

- Added the approved-artist navigation as a module-owned burgundy sidebar matching the established artist portal.
- Removed Home, My Account, and Account Security from the artist sidebar; Profile & Documents now owns artist profile, account email/name, password, verification, agreement, commission, and payout management.
- Hide artwork, virtual exhibition, sales, earnings, and payout features until the optional Store module is installed and enabled.
- Kept the update non-destructive: no core files, Store files, runtime data, or uninstall/purge operations are included.

## 1.5.22 - 2026-08-17

- Preserve the artwork foreign-key support index before removing the obsolete global artwork occupancy uniqueness constraint on MySQL.
- Keep multi-hall artwork placement scoped by environment without weakening referential integrity.

## 1.5.21 - 2026-08-16

- Removed the redundant artist application terms/manual review checkbox because it had no linked terms document.
- Kept the explicit Privacy Policy, versioned Artist Agreement, and artwork ownership declarations as the required registration consents.

## 1.5.20 - 2026-08-16

- Added an Agreements entry directly under the Exhibitions administrator sidebar menu.
- Restricted agreement management navigation to administrators with the Exhibitions settings permission.

## 1.5.19 - 2026-08-16

- Allow one available-for-sale artwork to be displayed once in each published 3D hall.
- Added hall selection and per-hall availability counts to the artist placement dashboard.
- Keep sale state shared across every hall so a sold artwork becomes non-interactive and marked sold in all of its placements.

## 1.5.18 - 2026-08-16

- Hid the technical Runtime GLB and Collision GLB download links from administrator-facing hall screens.
- Kept both private asset routes and runtime behavior unchanged for the 3D viewer and diagnostics.

## 1.5.17 - 2026-08-16

- Increased the authenticated hall upload, build, import, and publication limits to 20 actions per 10 minutes.
- Kept rate-limit protection enabled while allowing administrators to create a practical batch of halls without receiving HTTP 429 after five uploads.

## 1.5.16 - 2026-08-16

- Treat every Add new hall import as an independent publishable hall instead of another version of one global environment.
- Keep file replacements as versions of the selected hall so publishing a replacement only supersedes that hall's previous design.
- Make duplicated halls independent so both the original and its copy can be published simultaneously.
- Separate legacy halls that were previously grouped under the empty environment identifier while preserving their files and publication history.

## 1.5.15 - 2026-08-16

- Return administrators directly to the 3D Halls list after rooms and slots are imported.
- Keep the newly created hall in the ready state so preview, editing, and publication continue from the hall card.
- Prevent previous hall versions from being mixed into the completed Add new hall workflow.

## 1.5.14 - 2026-08-16

- Added Preview hall and Edit hall actions directly to every imported 3D hall card.
- Added a module-owned hall editor for hall names and per-room display names without changing validated runtime assets.
- Added a safe replacement handoff that creates a reviewable 3D version instead of overwriting the published hall.

## 1.5.13 - 2026-08-16

- Fixed new 3D hall imports when processed packages omit internal license/source URL fields; the importer now uses the private upload metadata recorded by the module.
- Unexpected import failures now return a safe retry message instead of exposing a generic HTTP 500 page.

## 1.5.12 - 2026-08-16

- Removed the hall file replacement and workflow restart box from Source history.

## 1.5.11 - 2026-08-16

- Added an authenticated private live preview for ready and published 3D hall versions.
- The preview reuses the production 3D viewer without publishing the hall or changing the public active version.
- Preview pages and manifests are private, non-cacheable, and excluded from search indexing.

## 1.5.10 — 2026-08-16

- Removed the creator, license, source URL, attribution, and confirmation fields from the hall upload UI.
- Combined GLB source upload and processed ZIP validation into one first step.
- Reduced the Add new hall workflow to three actions: upload files, import rooms, and publish.

## 1.5.9 — 2026-08-16

- Separated the Rooms management page from the Add new hall workflow page.
- The Rooms page now lists hall versions and their Duplicate/Delete actions, while Add new hall exclusively contains the four creation steps.
- Upload, processing, import, and final publication remain on the dedicated creation page until the workflow completes.

## 1.5.8 — 2026-08-16

- Deleting the last hall now removes its orphaned upload and processed-build state, then returns directly to Step 01.
- Shared source and runtime files remain protected while another hall version still references them.

## 1.5.7 — 2026-08-16

- Added hall controls to start a new GLB workflow, duplicate an imported hall as a safe draft version, and delete an unreferenced hall version.
- Hall duplication reuses immutable runtime files while copying rooms and artwork positions; deletion is blocked while artwork placements remain linked.
- Deleting the active hall safely promotes the newest remaining version, or returns the environment to draft when no version remains.

## 1.5.6 — 2026-08-16

- Changed the room workflow to show only the current actionable step instead of retaining completed step panels.
- Moved source replacement into the source-history maintenance area so it remains available without repeating Step 01.

## 1.5.5 — 2026-08-16

- Removed the external-processing instruction from the Step 02 heading.

## 1.5.4 — 2026-08-16

- Separated every room-pipeline action into its own full-width step instead of combining processing and import side by side.

## 1.5.3 — 2026-08-16

- Replaced the completed source-upload form with a persisted source and license summary plus an explicit replacement action.
- Added a guarded processed-build ZIP handoff with strict file, hash, GLB, Khronos, metadata, and license preflight.
- Added an explicit draft import action so publication remains a separate final review step.

## 1.5.2 — 2026-08-16

- Unified the Virtual Exhibition room workflow into one Rooms workspace for GLB upload, processing, import, and publication.
- Removed the Virtual Exhibition top tab bar from Rooms, Published Artworks, and Placement Requests.
- Redirected the legacy technical-loader page to the unified Rooms workspace while preserving its diagnostic asset endpoints.

## 1.5.1 — 2026-08-15

- Added the platform 2.5 plugin manifest contract and explicit ownership declarations.
- Replaced removed core identity and menu services with a module-owned user gateway and declarative menus.
- Preserved module-routed private assets, existing routes, permissions, lifecycle hooks, and Store-optional behavior.

## 1.4.0 — 2026-08-11

- Added VE-03 dynamic artwork projection into authored 3D slots at real-world physical scale.
- Added private sRGB JPEG derivatives for room, near-view, and artwork-popup delivery without cropping or visual enhancement.
- Rendered artwork pixels on an unlit, white, non-tone-mapped material while retaining normally lit frames and backing.
- Added blocker-aware center targeting within three metres and a native keyboard-accessible artwork information popup.
- Preserved sold artworks visibly with SOLD signage while disabling their target and popup interaction.
- Kept VE-01 data, users, artist workflows, and all non-Virtual-Exhibition module areas unchanged.

## 1.1.0 — 2026-08-06

- Added a standalone, permission-gated Exhibitions Settings workspace with nine responsive sections.
- Added typed `exhibitions.*` settings, exact decimal handling, versioned audit history, section reset, and scoped purge.
- Added artwork/artist/default commission resolution with immutable sale snapshots.
- Added fail-closed Store integration controls without direct Store-table coupling.
- Added settings, permissions, lifecycle, purge, and responsive visual regression evidence.
- Corrected image decode budgeting to use current allocated bytes while retaining explicit safety headroom.

## 1.0.1 — 2026-08-02

- Added the single-step `/artist/register` onboarding entry point.
- Creates the core account and submitted artist application in one database transaction.
- Reuses the existing application validation contract and private sample-artwork storage.
- Preserves email verification and returns the verified artist to the application status page.
- Updated the public “Become an Artist” menu entry to use the unified registration route.

## 1.0.0 — 2026-08-02

First stable production release, completing Phases 1–20.

### Delivered

- End-to-end artist application, verification, agreement, account, artwork, media, authenticity, pricing, review, publication, inventory, fulfillment, settlement, dispute, notification, reporting, security, and responsive experiences.
- Durable, idempotent Store 12.2 lifecycle consumption with ordered cursor recovery and fail-closed customer/order ownership.
- Versioned payout-outcome receiving without assuming payment-gateway ownership.
- Private-file, authorization, audit, privacy-redaction, CSV safety, upload signature, image-resource, and query-bound protections.
- Safe deactivate/uninstall behavior, explicit scoped purge, portable backup/restore rehearsal, and stable release operations.

### Fixed

- Preserve dispute `source` and `type` through the administration workspace subquery so artist, customer, ownership, and shipping tabs filter without SQL errors.

See `docs/phase-20-stable-production-release.md` for the production contract and release evidence.
