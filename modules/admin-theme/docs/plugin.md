# Admin Theme

Default protected admin dashboard theme for the Art INPA platform.

## Purpose

This plugin owns the admin dashboard visual layer through the existing platform plugin asset pipeline. The platform keeps ownership of menu data, routes, permissions, and Alpine interaction state; the theme owns the shell, sidebar, flyouts, colors, spacing, cards, forms, and responsive presentation.

## Install

1. Upload `admin-theme.zip` from the admin plugin installer.
2. Install the plugin.
3. Activate the plugin.
4. Confirm the admin layout contains an inline style block:

```text
data-plugin-admin-style="admin-theme"
data-plugin-admin-component="admin-navigation"
```

The default admin theme injects CSS from its ServiceProvider and does not write
to `public/platform/plugins`.

## Admin Theme Policy

- `admin-theme` is the default protected admin theme.
- The platform keeps exactly one admin theme active.
- Activating another admin theme disables `admin-theme`.
- Deactivating the active custom admin theme automatically restores
  `admin-theme`.
- Directly deactivating `admin-theme` is blocked while it is the fallback admin
  theme.

## Fast Editing

The main theme file is:

```text
resources/css/admin-theme.css
```

The sidebar has one dedicated, last-loaded component contract:

```text
resources/css/admin-navigation.css
```

Keep sidebar styling in that component file. Do not add navigation overrides to
core views or to the general theme file.

Page-specific admin presentation that previously lived in Blade `<style>`
blocks is owned by:

```text
resources/css/admin-pages.css
```

The first section of the file contains CSS variables. Edit those values for quick changes:

- `--ainpa-admin-bg`: page background.
- `--ainpa-admin-sidebar`: sidebar base color.
- `--ainpa-admin-sidebar-deep`: sidebar deep color.
- `--ainpa-admin-primary`: primary blue.
- `--ainpa-admin-primary-strong`: stronger active blue.
- `--ainpa-admin-radius`: default control radius.
- `--ainpa-admin-shadow`: default surface shadow.
- `--ainpa-admin-sidebar-width`: sidebar width. If changed, also verify page padding on desktop.

After editing a deployed CSS file directly, clear browser cache or change the file timestamp. If edited through the platform plugin package, reinstall/update the plugin so the asset publisher republishes the CSS.

## Compatibility

- Injects the general theme, the navigation component, and saved theme settings
  through the plugin ServiceProvider into the admin layout `styles` stack.
- Does not use the public asset publisher, so installing/updating the default
  admin theme does not require write access to `public/platform/plugins`.
- Targets the current admin layout classes such as `z4-admin-bar`, `z4-admin-sidebar`, `z4-admin-link`, and standard Tailwind utility classes used by admin pages.
- Keeps dynamic menu behavior in the platform and all navigation presentation in
  the plugin. Closed groups open as side flyouts on hover; clicked groups and the
  group containing the current route open inline.

## Verification

Run locally before upload:

```bash
php -l src/ArtInpaAdminProThemeServiceProvider.php
```

After upload and activation, check several admin pages:

- Dashboard
- Plugins
- Theme Editor
- Blog
- Media
- Settings

Confirm sidebars, cards, tables, forms, modals, and floating assistant widgets do not overlap.
