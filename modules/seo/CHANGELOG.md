# SEO Changelog

## 2.2.1 - 2026-11-18

- Added taxonomy sitemaps: blog categories (`category-sitemap.xml`), blog tags (`post_tag-sitemap.xml`), store categories (`product_category-sitemap.xml`) and brands (`product_brand-sitemap.xml`).
- Sitemap files now use standard Yoast-style names: `post-sitemap.xml`, `page-sitemap.xml`, `artwork-sitemap.xml`, `course-sitemap.xml`, `product-sitemap.xml`, `other-sitemap.xml`.
## 2.2.0 - 2026-11-18

- Sitemap index now exposes one sitemap per content type (`/sitemap-page.xml`, `/sitemap-blog_post.xml`, `/sitemap-lms_course.xml`, `/sitemap-store_product.xml`, `/sitemap-exhibition_artwork.xml`, `/sitemap-other.xml`).
- Chunks store their content type + page index so each section is paginated independently.
- New migration adds `content_type` and `page_index` to `seo_sitemap_chunks`.
## 2.1.2 - 2026-11-18

- Declare `seo_audit_runs` in `uninstall.tables` so the package passes plugin ownership validation.

## 2.1.1 - 2026-11-15

- Audit runs execute immediately from the admin panel (no queue worker required) and results appear without waiting.
- Every audit run is stored in `seo_audit_runs` (URLs, records, errors, warnings, duration, status) and the audit screen lists the last five runs.
- Each run records the exact checked URLs with their HTTP status, expandable under the run row.
- Sitemap generation keeps only the most recent runs (`seo.sitemap.keep_runs`, default 20) and deletes chunks of pruned runs.
- Sitemap chunk numbering starts at 1 (`/sitemap/1.xml`) and the hint text explains it.
- Sitemap `lastmod` values are normalised to W3C Datetime (ISO 8601).
- Arabic and English UI strings for the audit log.
