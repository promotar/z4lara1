<?php

return [
    'name' => 'Art INPA Platform',

    /*
    |--------------------------------------------------------------------------
    | Release label
    |--------------------------------------------------------------------------
    |
    | Human-facing platform release shown in the dashboard and update screens.
    | Bump this value for every release.
    |
    */
    'release' => '4.5.71',

    /*
    |--------------------------------------------------------------------------
    | Plugin compatibility version
    |--------------------------------------------------------------------------
    |
    | Compared against the "platform_version" constraint declared by every
    | installed module (for example ">=3.4.4 <4.0.0"). This value is a code
    | contract, not a marketing number: raising it past a module constraint
    | disables that module. It is intentionally NOT read from the environment
    | so an update can never leave a stale override behind.
    |
    */
    'version' => '3.4.4',
    'plugin_api' => '2.0',
    'asset_group' => env('PLATFORM_ASSET_GROUP', 'www-data'),
    'backup_file_manager_url' => env('BACKUP_FILE_MANAGER_URL', ''),
    'log_plugin_route_files' => (bool) env('PLATFORM_LOG_PLUGIN_ROUTES', false),
    'log_max_bytes' => (int) env('PLATFORM_LOG_MAX_BYTES', 20971520),
];
