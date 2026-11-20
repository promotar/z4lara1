# Certificates 1.0.1

Standalone feature plugin for Platform >=3.4.4 <4.0.0 and its existing Laravel 12 plugin API. No Blog, LMS, Page Builder or theme dependency. Administration uses the core x-app-layout component.

## Public presentation (1.0.1)

Public pages consume the existing core x-frontend-layout component with a custom head slot, so the site's current configured headers and footers remain shared, not copied. All certificate-specific templates, scoped styling, self-hosted Cairo fonts and standard Tabler certificate icon stay inside this plugin. The plugin does not call Page Builder classes or query another plugin's tables. With no configured layout sections, the existing core layout retains its own fallback behavior. Public noindex/referrer directives remain explicit; the layout's index/OG defaults are not emitted. Exact lookup, 30-attempt limit, private original images, four editable fields and administration are unchanged. No migration or data import is added.

## Data and access

Certificate posts expose exactly four editable fields: name, date, slug and image. The table adds only its technical primary key, with no publishing state, taxonomy field, source metadata, history or revision tables. Slugs are stored as text, never regenerated or lowercased. All four fields can be changed after saving. Original uploaded JPEG, PNG and WebP bytes are stored privately on the core local filesystem under certificates/images. Replacement removes the old image after a successful database update. The plugin does not generate certificates or QR codes.

Only core super-admin and admin roles can enter administration. Both receive plugin capability and exact route permissions through declared lifecycle hooks. Possessing a permission without an administrator role does not allow access. There is no public certificate archive, name search, suggestion API, export or sitemap registration.

The administration list supports a single search box matching the name or slug, including partial text, with literal wildcard handling and paginated results. This search is restricted to administrators and does not change the public exact-slug lookup.

## Public routes

- GET /certificate-search displays an empty form.
- POST /certificate-search accepts an exact full slug and redirects to /{slug}/. Case, zeros and suffixes matter. A single-use session grant ensures the redirect is not counted twice.
- GET /{slug}/ displays one certificate. Numeric identifier patterns and only existing nonnumeric slugs are registered, never an unrestricted root wildcard. Slug edits refresh patterns and clear an existing Laravel route cache. Root URLs do not receive a plugin prefix. Conflicting platform route/page slugs are rejected.
- GET /certificate-media/{slug} requires a short-lived signed URL issued by the certificate page. Image requests do not consume lookup attempts and cannot enumerate private files. Search and root page access share 30 attempts per IP per 60-second window. The 31st request gets 429 with Retry-After. IP recognition follows the platform's existing trusted proxy configuration.
- GET /certificate-assets/certificates.css serves the stylesheet from the plugin, with no source file published outside it.
- GET /certificate-assets/{asset} serves only the two bundled Cairo WOFF2 files and the standard certificate SVG. It does not accept arbitrary paths and does not consume lookup attempts. Font/icon licenses are distributed inside resources/assets.

All public plugin responses send X-Robots-Tag: noindex, nofollow, noarchive and private, no-store caching. Views repeat the robots directive. Robots directives are indexing requests, not authentication. Anyone who knows a current slug can display the certificate without logging in. Changing a slug leaves no alias or redirect, so the previous URL returns 404. Valid signed image URLs expire after ten minutes and serve the current image for the current slug.

## Packaging and lifecycle

Upload certificates-1.0.1.zip through the platform ZIP installer. When 1.0.0 already exists, review and confirm the normal plugin update; do not uninstall. The update replaces only plugin files and preserves certificate records and private images. module.json owns the certificates table and local certificates directory. Disabling the plugin preserves data; uninstalling through the core purge contract removes its owned data. Installation performs no WordPress import and contains no importer. Sites can use the create screen without importing anything.

## Practika migration

The one-off SQL migration is external to this plugin and is not distributed in its ZIP. It copies 381 original certificates from the approved database dump, defers source IDs 7042, 7244, 7517 and 8589, retains original slugs and original image bytes, and creates a separate audit file. No source-specific fields are required by this reusable plugin.
