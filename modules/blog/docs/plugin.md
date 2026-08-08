# Blog Plugin

Version: 2.9.0

## Purpose

The Blog plugin provides a WordPress Classic Editor style publishing workflow for Art INPA. It supports real post CRUD, TinyMCE visual editing, raw HTML editing, media upload/selection, SEO metadata, categories, tags, scheduling, previews, autosave, revisions, and frontend article pages.

## Admin Workflow

The single Blog admin entry point is:

```text
/admin/plugins/blog/posts
```

Primary screens:

- `/admin/plugins/blog` - choose the active site-wide Blog templates.
- `/admin/plugins/blog/posts` - all posts.
- `/admin/plugins/blog/posts/create` - add new post.
- `/admin/plugins/blog/categories` - all categories.
- `/admin/plugins/blog/tags` - create and manage tags.
- `/admin/plugins/blog/templates` - create reusable HTML/CSS post, archive, search, card, slider, and custom templates with an isolated live preview.

## Post Template Studio

The template studio stores templates in `blog_templates` with a stable slug,
free template category, activation status, HTML, CSS, and a Media Library preview
image. The editor renders changes immediately in a sandboxed iframe so template
CSS cannot leak into the admin interface.

Templates also have a dedicated JavaScript tab. JavaScript is stored separately
from HTML/CSS and served through `/blog/assets/templates/{slug}.js` with the
correct JavaScript content type. Locked defaults keep this file read-only while
copies can provide their own behavior.

Authorized builder integrations can read active templates from:

```text
/admin/plugins/blog/templates/catalog
```

The catalog returns stable template IDs/slugs plus HTML, CSS, category, preview
image, and update timestamp. This keeps future VvvebJs integration plugin-owned
and avoids adding Blog tables or namespaces to the platform core.

### Page Builder extension contract

Blog owns the `Blog Template` VvvebJs element without placing Blog domain code in
Page Builder. `hooks.php` registers it through the documented
`plugin.page-builder.editor.extensions` and `plugin.page-builder.frontend.html`
filters. The editor stores only an inert `data-blog-template-slug` slot and Blog resolves that
slot through `TemplateRenderer` on the server. Since plugin hooks and assets load
only for active plugins, disabling or uninstalling Blog removes the element and
renderer automatically; reactivation restores previously saved slots.

Collection elements can enable independent server-side pagination. Every saved
element receives its own stable instance key, preventing two Blog templates on
the same page from changing each other's page. Available navigation modes are
page numbers, previous/next, and progressively enhanced Load More. The per-page
limit is constrained to 1-24 posts and all modes retain crawlable links.

The Page Builder element provides Grid, Cards, and Slider display modes, separate
desktop/tablet/mobile column counts, and a 0-120px column gap. Layout rules are
scoped to each builder instance. Slider controls live in the independent
`blog-template-layout.js` asset and Load More refreshes the same slider instance
without rebuilding unrelated Blog elements.

Collection templates can repeat markup for every supplied post:

```html
{{#posts}}
<article>
    <img src="{{featured_image}}" alt="{{title}}">
    <h2><a href="{{url}}">{{title}}</a></h2>
    <p>{{excerpt}}</p>
</article>
{{/posts}}
```

The `Classes You Can Use` editor tab is the canonical token reference. It covers
post content, image data, primary and multiple taxonomies, author and dates,
publishing fields, layout keys, SEO metadata, and archive globals. Clicking a
token copies it and inserts it into the HTML editor. Passwords are deliberately
excluded from template output.

The studio supports nested `{{#tags}} ... {{/tags}}` and
`{{#categories}} ... {{/categories}}` loops inside a post loop. Both expose
`{{name}}`, `{{slug}}`, and `{{url}}`. The template studio uses six sample posts
for live previews; `TemplateRenderer` applies the same syntax to real Blog
`Post` collections for future VvvebJs elements.

### Protected defaults and site-wide selection

Installation supplies five protected templates: Single Post, Archive, Category,
Search Results, and Post Slider. Their source remains readable in the studio but
the application rejects updates and deletion. `Copy Template` creates a normal
draft copy whose code, identity, status, and preview image can be changed.

`/admin/plugins/blog` selects one active template for each public page context:
single post, archive, category, and search. Only an active template whose
category matches the context can be selected. If a selected custom template is
later unavailable, rendering falls back to the matching protected default.

## Editor

The post editor uses TinyMCE loaded from jsDelivr:

```text
https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js
```

Supported controls include:

- bold, italic, underline, strikethrough
- paragraph and H1-H6 block formats
- font size
- text and background color
- ordered and unordered lists
- blockquote
- links and unlink through TinyMCE
- images and media insert
- tables
- alignment
- code block
- raw HTML Code tab
- undo/redo
- fullscreen
- word count
- paste cleanup through TinyMCE

The Visual and Code tabs synchronize before save, preview, autosave, and publish.

## Media Library

Media endpoints:

- `GET /admin/plugins/blog/media`
- `POST /admin/plugins/blog/media`
- `PATCH /admin/plugins/blog/media/{media}`
- `DELETE /admin/plugins/blog/media/{media}`

Uploads are stored on the Laravel `public` disk under:

```text
blog/media
```

Media metadata includes title, alt text, caption, mime type, size, width, height, uploader, and URL.

## Database Tables

Core tables:

- `blog_posts`
- `blog_categories`
- `blog_tags`
- `blog_post_tag`
- `blog_category_post`
- `blog_media`
- `blog_post_revisions`
- `blog_post_meta`
- `blog_templates`
- `blog_template_settings`

`blog_posts` supports title, slug, content, excerpt, status, visibility, password, published and scheduled timestamps, author, featured image, layout/template, SEO title/description, focus keyword, canonical URL, robots flags, schema type, and soft deletes.

## Publishing Rules

Public frontend pages only show posts where:

- `status = published`
- `visibility = public`
- `published_at` is null or in the past
- `scheduled_at` is null or in the past

Private and password-protected posts do not appear publicly.

## Frontend Routes

- `/blog`
- `/blog/{slug}`
- `/blog/category/{slug}`
- `/blog/tag/{slug}`
- `/blog/search?q=term`
- `/blog/assets/blog.css` (plugin-owned stylesheet; no managed public asset directory required)

## VvvebJs Frontend Menu

Installation registers the database-backed frontend menu `blog.frontend` with a
top-level Blog item and a Categories submenu. It is available directly from the
Frontend Menu element in VvvebJs and can be extended or reordered from the
platform Frontend Menus screen.

Frontend output places route-specific SEO metadata before CSS and JavaScript, and includes canonical/robots, complete Open Graph and Twitter metadata, article timestamps/taxonomies, and valid JSON-LD Article/BlogPosting/NewsArticle plus breadcrumb schema. Category and archive pages expose CollectionPage schema.

Category images use the same shared platform Media Library picker and immediate upload workflow as post featured images. Media remains library-owned, so deleting a category never removes an asset that another post or page may still use.

The SEO score uses one weighted formula in the live editor and on save. Its Unicode-aware word counter supports Arabic and Latin content and evaluates focus-keyword placement, content/title/description length, featured image, category, and tags.

The post list uses compact accordion cards. Each card provides a focused quick editor for title, slug, publishing status, publish/scheduled timestamps, and publisher, while the full editor remains available for content and SEO work. The obsolete trash-confirmation modal and all of its CSS/JavaScript were removed.

Post management includes All, Published, Scheduled, and Trash filters with selection controls above and below the accordion list. Category management includes All and Trash filters. Both support bulk trash and restore operations, permanent deletion of selected trashed records, and empty-trash actions. Permanent-delete handlers reject active records even if a request is manually altered.

The post list search bar filters by title/slug/keyword and provides independent
category and tag selectors in every post status tab. Publishing state remains
controlled by the All, Published, Scheduled, and Trash tabs rather than a
duplicate status dropdown.

## Security

- Admin routes use the platform admin middleware stack.
- CSRF is used for form and Ajax requests.
- Uploads are validated by mime type and size.
- Script tags, inline event handlers, and `javascript:` URLs are stripped from content unless a future explicit super-admin DB setting enables script content.
- The public frontend renders stored HTML only after save-time sanitization.

## Verification

Manual smoke checks performed:

- create draft data path
- publish path
- scheduled post hidden until time
- private post hidden publicly
- category relation saved
- tag relation saved
- revision saved
- frontend article renders HTML
- schema output exists
- script tag stripped
- template/layout saved
- media upload creates `blog_media`
- media can be set as featured image
- featured image renders on frontend
