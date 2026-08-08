<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $basePath = dirname(__DIR__);
        $environment = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => $basePath.'/bootstrap/cache/testing-config.php',
            'APP_EVENTS_CACHE' => $basePath.'/bootstrap/cache/testing-events.php',
            'APP_ROUTES_CACHE' => $basePath.'/bootstrap/cache/testing-routes.php',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = require $basePath.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if (
            $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new RuntimeException('Tests refused to start outside isolated SQLite memory storage.');
        }

        return $app;
    }
}
