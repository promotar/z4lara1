# Plugin Core

This directory is reserved for the Core plugin lifecycle:

- PluginValidator: validate uploaded ZIP and module.json.
- PluginInstaller: extract, validate, move, migrate, and register plugins.
- PluginManager: load only active plugin service providers.
- PluginActivator: mark plugin active and clear caches.
- PluginDeactivator: mark plugin inactive without deleting data.
- PluginUninstaller: explicit destructive uninstall.

No business plugin logic belongs in Core.
