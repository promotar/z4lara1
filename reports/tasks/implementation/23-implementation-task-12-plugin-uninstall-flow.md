# Report: Implementation Task 12 - Plugin Uninstall Flow

## Date

2026-06-21

## Task

Complete `Implementation Task 12: Plugin Uninstall Flow`.

## Scope

This task implemented the plugin uninstall flow only. It did not reactivate, deactivate, install, load routes, load service providers, build a UI, add marketplace behavior, add updates, add licensing, or delete physical plugin source files.

## What Was Implemented

- Added the uninstall orchestration flow:
  `app/Platform/Core/Plugins/Uninstall/PluginUninstallFlow.php`
- Added uninstall validation, backup, script, table, permission, menu, settings, asset, and cache helper classes under:
  `app/Platform/Core/Plugins/Uninstall/`
- Updated the existing plugin uninstaller:
  `app/Platform/Core/Services/PluginUninstaller.php`
- Updated `PluginManager::uninstall()` to return the structured uninstall result.
- Updated `PluginRuntimeRegistry` so uninstall can remove plugin runtime state.

## Uninstall Flow

The uninstall flow now:

1. Resolves the plugin through the existing `PluginUninstaller`.
2. Blocks uninstall when the plugin is `active`.
3. Allows uninstall only from `installed` or `disabled`.
4. Blocks uninstall if active plugins depend on the target plugin.
5. Creates a pre-uninstall checkpoint under `storage/app/platform/plugin-uninstall-checkpoints`.
6. Runs a declared `uninstall.php` script only when explicitly declared in the manifest.
7. Drops only manifest-declared plugin-owned tables and refuses protected platform tables.
8. Removes declared or plugin-namespaced permissions.
9. Removes the plugin-owned menu registry entry.
10. Removes plugin-owned settings keys from the existing JSON settings store.
11. Removes published plugin assets only from approved `public/plugins` or `public/vendor/plugins` paths.
12. Removes plugin runtime state.
13. Clears relevant Laravel caches through the existing cache cleaner.
14. Deletes the plugin database record only after all destructive steps complete.
15. Returns a structured result with success, plugin slug, previous status, completed steps, failed step, removed resources, dependency blockers, and message.

## Destructive Guards

- Active plugins are blocked before any destructive step.
- Active dependent plugins block uninstall.
- Uninstall scripts must stay inside the plugin directory and must be named `uninstall.php`.
- Tables are dropped only when explicitly declared in uninstall manifest metadata.
- Core platform tables, permission tables, plugin tables, migration tables, and checklist tables are protected.
- Asset removal is limited to approved public plugin asset paths.
- Physical plugin source files are not deleted.
- The plugin database record is deleted only as the final successful step.

## Verification Performed

- Ran PHP syntax checks on all new uninstall classes and changed services on the server.
- Regenerated optimized Composer autoload files as `www-data`.
- Ran `php artisan about --only=environment` successfully.
- Ran a smoke test that verified:
  - active plugin uninstall is blocked
  - active dependent plugin blocks uninstall
  - disabled plugin uninstall succeeds
  - declared uninstall script runs
  - declared plugin-owned table is dropped
  - plugin permission is removed
  - plugin menu registry entry is removed
  - plugin-owned setting is removed while unrelated setting is preserved
  - published plugin assets are removed
  - plugin runtime state is removed
  - plugin database record is removed at the end
  - physical plugin source file remains during uninstall verification
- Confirmed smoke-test rows, temp table, and temp files were cleaned after verification.

## Server Files Added or Updated

- `app/Platform/Core/Plugins/Uninstall/PluginUninstallFlow.php`
- `app/Platform/Core/Plugins/Uninstall/PluginUninstallValidator.php`
- `app/Platform/Core/Plugins/Uninstall/PluginUninstallBackup.php`
- `app/Platform/Core/Plugins/Uninstall/PluginUninstallScriptRunner.php`
- `app/Platform/Core/Plugins/Uninstall/PluginTableDropper.php`
- `app/Platform/Core/Plugins/Uninstall/PluginPermissionRemover.php`
- `app/Platform/Core/Plugins/Uninstall/PluginMenuRemover.php`
- `app/Platform/Core/Plugins/Uninstall/PluginSettingsRemover.php`
- `app/Platform/Core/Plugins/Uninstall/PluginAssetRemover.php`
- `app/Platform/Core/Plugins/Uninstall/PluginUninstallCacheClearer.php`
- `app/Platform/Core/Services/PluginUninstaller.php`
- `app/Platform/Core/Services/PluginManager.php`
- `app/Platform/Core/Services/PluginRuntimeRegistry.php`

## Notes

- No Laravel core or vendor files were modified.
- No external packages were installed.
- No migrations were added for this task.
- `DECISIONS.md` was not updated because this task followed the approved uninstall architecture and did not add a new architectural decision.

## Result

`Implementation Task 12: Plugin Uninstall Flow` is implemented and verified on the server.
