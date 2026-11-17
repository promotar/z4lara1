<?php

namespace App\Platform\Core\Registry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlatformRegistry
{
    public function functions(): array
    {
        return $this->entries('functions');
    }

    public function hooks(): array
    {
        return $this->entries('hooks');
    }

    public function routes(): array
    {
        return $this->entries('routes');
    }

    public function functionIsRegistered(string $name): bool
    {
        return $this->isActive($this->functions(), $name);
    }

    public function hookIsRegistered(string $name): bool
    {
        return $this->isActive($this->hooks(), $name);
    }

    public function routeIsRegistered(?string $name, ?string $uri = null, ?string $method = null): bool
    {
        foreach ($this->routes() as $registered => $definition) {
            if (! $this->definitionIsActive($definition)) {
                continue;
            }

            if ($name !== null && $name !== '' && ($registered === $name || Str::is($registered, $name))) {
                return true;
            }

            if ($this->definitionMatchesRequest($definition, $uri, $method)) {
                return true;
            }
        }

        return false;
    }

    public function pluginRouteFileIsRegistered(string $pluginSlug, string $type): bool
    {
        $plugin = $this->pluginManifests()[$pluginSlug] ?? null;

        if (is_array($plugin) && data_get($plugin, "routes.{$type}") !== null) {
            return true;
        }

        $files = config('platform_registry.plugin_route_files', []);
        $definition = $files[$pluginSlug] ?? $files['*'] ?? [];

        return (bool) ($definition[$type] ?? false);
    }

    public function unregisteredRoutes(): array
    {
        $unregistered = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || $name === '') {
                continue;
            }

            if (! $this->routeIsRegistered($name, $route->uri(), $route->methods()[0] ?? null)) {
                $unregistered[] = [
                    'name' => $name,
                    'method' => implode('|', $route->methods()),
                    'uri' => $route->uri(),
                ];
            }
        }

        return $unregistered;
    }

    private function entries(string $key): array
    {
        $entries = config("platform_registry.{$key}", []);
        $entries = is_array($entries) ? $entries : [];
        $entries = array_replace($entries, $this->databaseEntries($key));

        foreach ($this->pluginManifests() as $slug => $manifest) {
            $status = (string) ($manifest['_plugin_status'] ?? 'installed');
            $entries = array_replace($entries, $this->pluginEntries($manifest, $key, $slug, $status));
        }

        return $entries;
    }

    private function databaseEntries(string $key): array
    {
        try {
            if (! Schema::hasTable('platform_registry_entries')) {
                return [];
            }

            return DB::table('platform_registry_entries')
                ->where('registry_type', $key)
                ->where('status', 'active')
                ->get(['registry_key', 'payload', 'status'])
                ->mapWithKeys(function (object $entry): array {
                    $payload = is_string($entry->payload)
                        ? json_decode($entry->payload, true)
                        : $entry->payload;

                    $payload = is_array($payload) ? $payload : [];
                    $payload['status'] = $payload['status'] ?? $entry->status;

                    return [(string) $entry->registry_key => $payload];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function pluginManifests(): array
    {
        try {
            $plugins = DB::table('plugins')->select('slug', 'status', 'manifest')->get();
        } catch (\Throwable) {
            return [];
        }

        $manifests = [];

        foreach ($plugins as $plugin) {
            $manifest = $plugin->manifest;

            if (is_string($manifest)) {
                $manifest = json_decode($manifest, true);
            }

            if (! is_array($manifest)) {
                continue;
            }

            $manifest['_plugin_status'] = (string) $plugin->status;
            $manifests[(string) $plugin->slug] = $manifest;
        }

        return $manifests;
    }

    private function pluginEntries(array $manifest, string $key, string $slug, string $pluginStatus): array
    {
        $status = $pluginStatus === 'active' ? 'active' : 'inactive';

        return match ($key) {
            'functions' => $this->normalizeNamedEntries(data_get($manifest, 'functions', []), $slug, 'function', $status),
            'hooks' => $this->normalizeNamedEntries(data_get($manifest, 'hooks', []), $slug, 'hook', $status),
            'routes' => $this->routeEntries($manifest, $slug, $status),
            default => [],
        };
    }

    private function normalizeNamedEntries(mixed $entries, string $slug, string $type, string $status): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $name => $definition) {
            if (is_int($name) && is_string($definition)) {
                $name = $definition;
                $definition = [];
            }

            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $definition = is_array($definition) ? $definition : [];
            $definition['plugin'] = $slug;
            $definition['type'] = $definition['type'] ?? $type;
            $definition['status'] = $definition['status'] ?? $status;
            $normalized["plugin.{$slug}.{$name}"] = $definition;
        }

        return $normalized;
    }

    private function routeEntries(array $manifest, string $slug, string $status): array
    {
        $routes = data_get($manifest, 'routes', []);

        if (! is_array($routes)) {
            return [];
        }

        $entries = [];

        foreach ($routes as $type => $definition) {
            if (! is_string($type) || ! is_array($definition)) {
                continue;
            }

            $prefix = trim((string) ($definition['prefix'] ?? match ($type) {
                'admin' => 'admin/plugins/'.$slug,
                'api' => 'plugins/'.$slug,
                default => $slug,
            }), '/');
            $methods = $definition['methods'] ?? ['GET', 'HEAD', 'POST', 'PATCH', 'DELETE'];

            $entries["plugin.{$slug}.routes.{$type}"] = [
                'uri' => $prefix === '' ? '*' : $prefix.'*',
                'methods' => is_array($methods) ? $methods : ['GET', 'HEAD'],
                'description' => "{$slug} {$type} plugin routes",
                'plugin' => $slug,
                'status' => $status,
            ];
        }

        $registeredRoutes = data_get($manifest, 'registry.routes', []);

        if (is_array($registeredRoutes)) {
            foreach ($registeredRoutes as $name => $definition) {
                if (is_int($name) && is_array($definition)) {
                    $name = $definition['name'] ?? null;
                }

                if (! is_string($name) || trim($name) === '' || ! is_array($definition)) {
                    continue;
                }

                $definition['plugin'] = $slug;
                $definition['status'] = $definition['status'] ?? $status;
                $definition['methods'] = isset($definition['methods']) && is_array($definition['methods'])
                    ? $definition['methods']
                    : ['GET', 'HEAD'];
                $entries[$name] = $definition;
            }
        }

        return $entries;
    }

    private function isActive(array $entries, string $name): bool
    {
        $definition = $entries[$name] ?? null;

        return $definition !== null && $this->definitionIsActive($definition);
    }

    private function definitionMatchesRequest(mixed $definition, ?string $uri, ?string $method): bool
    {
        if (! is_array($definition) || $uri === null || $method === null) {
            return false;
        }

        $registeredUri = $definition['uri'] ?? null;

        if (! is_string($registeredUri) || ! Str::is($registeredUri, trim($uri, '/'))) {
            return false;
        }

        $methods = $definition['methods'] ?? [];

        if (! is_array($methods)) {
            return false;
        }

        return in_array(strtoupper($method), array_map('strtoupper', $methods), true);
    }

    private function definitionIsActive(mixed $definition): bool
    {
        if (is_array($definition)) {
            return ($definition['status'] ?? 'active') === 'active';
        }

        return (bool) $definition;
    }
}
