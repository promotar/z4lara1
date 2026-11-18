<?php

return [
    'name' => 'Art INPA Platform',
    'version' => env('PLATFORM_VERSION', '3.4.4'),
    'plugin_api' => '2.0',
    'asset_group' => env('PLATFORM_ASSET_GROUP', 'www-data'),
    'backup_file_manager_url' => env('BACKUP_FILE_MANAGER_URL', ''),
    'log_plugin_route_files' => (bool) env('PLATFORM_LOG_PLUGIN_ROUTES', false),
    'log_max_bytes' => (int) env('PLATFORM_LOG_MAX_BYTES', 20971520),
];
