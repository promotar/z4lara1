<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Verification for the runtime plugin route pipeline.
 *
 * Plugin routes are registered per request from modules/<slug>/routes/*.php
 * and wrapped by EnsurePluginIsActive. A route cache (or a route registered
 * outside the loader) breaks that contract, so both are reported here.
 *
 * Usage:
 *   php artisan platform:routes-check
 *   php artisan platform:routes-check --json
 */
class PlatformRoutesCheck extends Command
{
    protected $signature = 'platform:routes-check {--json : Output machine readable JSON}';

    protected $description = 'Verify plugin routes load at runtime (no route cache) and carry the plugin guard.';

    public function handle(): int
    {
        $cached = app()->routesAreCached();
        $total = 0;
        $pluginRoutes = 0;
        $guarded = 0;
        $unguarded = [];
        $duplicates = [];

        $seen = [];

        foreach (Route::getRoutes() as $route) {
            $total++;
            $action = (string) $route->getActionName();
            $name = (string) ($route->getName() ?? '');
            $uri = $route->uri();
            $methods = $route->methods();

            if ($name !== '') {
                $identity = implode('|', $methods).' '.$uri;

                if (isset($seen[$name]) && $seen[$name] !== $identity) {
                    $duplicates[] = $name;
                }

                $seen[$name] = $identity;
            }

            if (! str_contains($action, 'Modules\\')) {
                continue;
            }

            $pluginRoutes++;

            $isGuarded = false;

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_contains($middleware, 'EnsurePluginIsActive')) {
                    $isGuarded = true;

                    break;
                }
            }

            if ($isGuarded) {
                $guarded++;

                continue;
            }

            $unguarded[] = [
                'name' => $name !== '' ? $name : '(unnamed)',
                'uri' => $uri,
                'action' => $action,
            ];
        }

        $failures = [];

        if ($cached) {
            $failures[] = 'Route cache is active: plugin routes are frozen and a disabled plugin keeps its routes. Run `php artisan route:clear`.';
        }

        foreach ($unguarded as $route) {
            $failures[] = 'Plugin route without EnsurePluginIsActive: '.$route['name'].' ('.$route['uri'].')';
        }

        foreach (array_unique($duplicates) as $name) {
            $failures[] = 'Duplicate route name: '.$name;
        }

        $ok = $failures === [];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $ok,
                'route_cache' => $cached,
                'routes_total' => $total,
                'plugin_routes' => $pluginRoutes,
                'guarded' => $guarded,
                'unguarded' => $unguarded,
                'duplicate_names' => array_values(array_unique($duplicates)),
                'failures' => $failures,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $ok ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
        }

        $this->table(['Metric', 'Value'], [
            ['route cache', $cached ? 'ACTIVE (bad)' : 'off'],
            ['routes total', (string) $total],
            ['plugin routes', (string) $pluginRoutes],
            ['guarded', (string) $guarded],
            ['unguarded', (string) count($unguarded)],
            ['duplicate names', (string) count(array_unique($duplicates))],
        ]);

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($ok) {
            $this->info('OK: plugin routes are runtime-loaded and guarded.');
        }

        return $ok ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
    }
}
