<?php

// Local diagnostics/package checks. No importer or certificate data is bundled here.
use App\Platform\Core\Services\PluginManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$root = dirname(__DIR__);

if (($argv[1] ?? '') === '--install-local') {
    if (getenv('CERTIFICATES_LOCAL_INSTALL') !== '1') {
        throw new RuntimeException('Local installation refused in production.');
    }
    $manager = app(PluginManager::class);
    $existing = $manager->findBySlug('certificates');
    if ($existing === null) {
        $existing = $manager->install($root);
    }
    if ($existing->status !== 'active') {
        $existing = $manager->activate($existing);
    }
    echo json_encode(['slug' => $existing->slug, 'status' => $existing->status], JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}

if (($argv[1] ?? '') === '--check-zip') {
    $archive = $argv[2] ?? throw new RuntimeException('ZIP path required.');
    $directory = sys_get_temp_dir().'/certificates-zip-check-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $controller = app(App\Http\Controllers\Admin\PluginController::class);
    $method = new ReflectionMethod($controller, 'extractAndValidateZip');
    $extracted = $method->invoke($controller, $archive, $directory);
    $manifest = app(PluginManager::class)->validatePackage($extracted);
    echo json_encode(['zip_validation' => 'passed', 'slug' => $manifest->slug, 'version' => $manifest->version], JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}

echo json_encode([
    'platform_version' => config('platform.version'),
    'environment' => app()->environment(),
    'cache_store' => config('cache.default'),
    'local_storage_root' => config('filesystems.disks.local.root'),
    'plugins' => DB::table('plugins')->get(['slug', 'status', 'version'])->toArray(),
    'role_names' => DB::table('roles')->pluck('name')->all(),
    'certificates' => Schema::hasTable('certificates') ? DB::table('certificates')->count() : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
