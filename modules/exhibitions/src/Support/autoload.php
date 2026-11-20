<?php

if (! defined('Z4_EXHIBITION_AUTOLOADER_REGISTERED')) {
    define('Z4_EXHIBITION_AUTOLOADER_REGISTERED', true);

    spl_autoload_register(static function (string $class): void {
        $prefix = 'Modules\\Exhibition\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = dirname(__DIR__).DIRECTORY_SEPARATOR.$relative.'.php';

        if (is_file($file)) {
            require_once $file;
        }
    });
}
