# SEO Plugin

Essential SEO management for ART INPA.

## Sections

- Dashboard: indexable content, active redirects, open issues and sitemap status.
- Settings: website information, titles/indexing defaults, social sharing and organization information.
- Sitemap: enable/disable, URL limit, image options and per-content-type inclusion.
- Redirects: 301, 302 and 410 rules with enable/disable controls.
- SEO Audit: content records missing SEO metadata, noindex records, missing URLs, 404s and indexable content absent from the sitemap.

## Default value resolution

Each SEO value is resolved in this order:

1. The custom value on the content record.
2. The configured global default.
3. The platform/Core fallback.

Defaults are resolved at render time and are not copied into every content row.

## Core integration

- Registers the global `SeoPluginContributor` at priority `100`.
- Uses the Core `SeoData` pipeline and head renderer.
- Provides sitemap and robots providers while active.
- Registers redirect middleware globally while active.
- Inactive modules are excluded from content-type settings, audits and sitemap entries.

## Sitemap

The sitemap respects the global enabled setting, the per-content-type switches,
the current indexability policy, redirects, locale URL strategy and configured
URL limit.

## Audit

The audit is content-focused and intentionally limited to practical checks. It
does not perform keyword research, backlink analysis, rankings, AI scoring or
external SEO integrations.

## Lifecycle and data

- Deactivate keeps SEO data.
- Uninstall archives SEO data for the retention window.
- Purge permanently removes module SEO settings, metadata and redirects only
  after explicit confirmation.
