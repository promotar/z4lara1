# Blog Changelog

## 2.10.0 - 2026-11-19

- Category translations: blog_category_translations table, per-locale slugs, and the same language tabs used by posts (base = site language).
- Frontend category names, chips and category archive URLs follow the active locale; legacy [embed] shortcodes render as responsive YouTube players.


## 2.9.14 - 2026-11-15

- The content language of a post is now the site language: writing in the site-language tab updates the post itself, and other languages are optional translations saved separately.
- The editor language tab for the site language no longer shows a “+” badge; other tabs show + when no translation exists and ✓ when one does.
- Opening a language tab now loads that language’s saved translation (previously the English tab could display the site-language content) and a missing translation starts with empty fields instead of a copy of the base content.
- "View Public Post" opens the post in the editor’s language and switches the visitor session language to match, using the translation permalink when available.
- The editor media modal keeps its footer and Insert button on screen at any viewport height, and an image can be inserted with a double-click.
- The editor uses the browser context menu (copy/paste/spellcheck) instead of a limited custom menu.
- The stylesheet link is versioned by file time so CSS changes reach visitors without a hard refresh, and the cache window is reduced to five minutes.
- Blog archives, categories, tags and search show a clear empty state with links to all articles and all categories, and empty pages are marked noindex.

## 2.9.11 - 2026-10-15

- The post sub-actions in the account sidebar now stay hidden until the Posts tab is active.

## 2.9.10 - 2026-10-15

- Rename the account Write a Post section to Posts in the navigation and section heading, and label its create action Add new post.

## 2.9.9 - 2026-10-15

- Writers can delete their own posts from the account Write a Post list and from the writer workspace; deleted posts move to a new Deleted posts view where they can be restored or permanently removed.
- The writer workspace keeps the account navigation sidebar with quick sub-actions: All posts, Add new post and Deleted posts.
- The account sidebar shows the same post sub-actions under Write a Post.

## 2.9.8 - 2026-10-15

- Blog public pages and the supplied default templates now take their colours from the active platform theme instead of a fixed palette.
- Blog administration screens follow the administration theme accent, text and muted colours, including the primary action colour.
- Previous colour values are kept as fallbacks.

## 2.9.7 - 2026-09-08

- Restore readable author search-result colors by isolating result rows from the general button style.
- Improve Save Draft, Submit for Review, and Preview Draft controls across desktop, tablet, and mobile layouts.

## 2.9.6 - 2026-09-05

- Make the initial Blog schema migration safe to replay during plugin upgrades.
- Preserve every existing category, tag, post, and post-tag relation when migration tracking is rebuilt.

## 2.9.5 - 2026-09-05

- Corrected the platform compatibility range to support Core 3.4.3 and newer 3.x releases.

## 2.9.4 - 2026-09-05

- Added managed Blog authors selected from existing platform users.
- Added per-author direct-publishing or editorial-review policy.
- Added a secure frontend post editor, author-owned post list, and account tab.
- Limited the frontend media library to images uploaded by the signed-in author while using platform media storage.
- Added an administrator review queue without changing existing published content.

## 2.9.3 - 2026-09-04

- Follow the platform language and writing direction for frontend rendering, dates, and structured-data language metadata.
- Preserve existing post content when the platform language changes.
- Require platform Core 3.4.4 or newer.

## 2.9.2 - 2026-08-31

- Publish the current stable Blog implementation as a synchronized standalone plugin release.
- Keep the complete post, media, taxonomy, template, revision, autosave, preview, scheduling, and frontend article workflows unchanged.
- Align the packaged manifest and local platform registry on the same release number.

## 2.9.1

- Previous stable Blog release.
