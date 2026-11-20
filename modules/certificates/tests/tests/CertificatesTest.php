<?php

namespace Modules\Certificates\Tests;

use App\Installation\InstallationState;
use App\Models\User;
use App\Platform\Core\Menus\MenuManager;
use App\Platform\Core\Services\PluginManager;
use App\Platform\Core\Services\PluginRouteLoader;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Certificates\CertificatesServiceProvider;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Support\CertificateRoutes;
use Spatie\Permission\Models\Role;

class CertificatesTest extends TestCase
{
    private string $runtime;

    public function createApplication(): Application
    {
        $base = dirname(__DIR__, 3);
        $this->runtime = sys_get_temp_dir().'/certificates-test-'.bin2hex(random_bytes(8));
        $files = new \Illuminate\Filesystem\Filesystem;
        foreach (['bootstrap/cache', 'app/platform', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'environment'] as $directory) {
            $files->ensureDirectoryExists($this->runtime.'/'.$directory);
        }
        $files->put($this->runtime.'/environment/.env', '');
        foreach ([
            'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $this->runtime.'/bootstrap/cache/config.php',
            'APP_ROUTES_CACHE' => $this->runtime.'/bootstrap/cache/routes.php',
            'APP_EVENTS_CACHE' => $this->runtime.'/bootstrap/cache/events.php',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4', 'INSTALLATION_COMPLETE' => '1',
        ] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('Modules\\Certificates\\', dirname(__DIR__).'/src', true);
        }
        $app = require $base.'/bootstrap/app.php';
        $app->useStoragePath($this->runtime);
        $app->useEnvironmentPath($this->runtime.'/environment');
        $app->make(Kernel::class)->bootstrap();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests refused outside isolated SQLite memory storage.');
        }
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        File::put($this->runtime.'/installation.complete', 'installed');
        $this->app->instance(InstallationState::class, new InstallationState($this->runtime.'/installation.env', $this->runtime.'/environment/.env', $this->runtime.'/installation.complete'));
        Artisan::call('migrate', ['--force' => true]);
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('user', 'web');
        $plugin = app(PluginManager::class)->install(dirname(__DIR__));
        app(PluginManager::class)->activate($plugin);
        $this->app->register(CertificatesServiceProvider::class);
        app(PluginRouteLoader::class)->loadWebRoutes();
        app(PluginRouteLoader::class)->loadAdminRoutes();
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
        config(['filesystems.disks.local.root' => $this->runtime.'/private-images']);
    }

    protected function tearDown(): void
    {
        if (isset($this->runtime)) {
            File::deleteDirectory($this->runtime);
        }
        parent::tearDown();
    }

    private function certificate(string $slug = '269501-2', string $name = 'Certificate owner'): Certificate
    {
        $image = UploadedFile::fake()->image('original.jpg', 2000, 1414);
        $path = $image->store('certificates/images', 'local');
        return Certificate::create(['name' => $name, 'date' => '2026-09-01 10:00:00', 'slug' => $slug, 'image' => $path]);
    }

    private function administrator(string $role = 'admin'): User
    {
        $user = User::create([
            'name' => 'Certificate test administrator',
            'email' => bin2hex(random_bytes(8)).'@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('local-test-only'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        return $user;
    }

    public function test_plugin_owns_only_four_business_fields_and_primary_key(): void
    {
        $this->assertSame(['id', 'name', 'date', 'slug', 'image'], Schema::getColumnListing('certificates'));
        $manifest = json_decode(file_get_contents(dirname(__DIR__).'/module.json'), true);
        $this->assertSame([], $manifest['dependencies']);
        $this->assertSame(['certificates'], $manifest['uninstall']['tables']);
    }

    public function test_public_form_contains_no_certificate_names_or_archive(): void
    {
        $this->certificate();
        $this->certificate('000123-4', 'Second secret owner');
        $this->get('/certificate-search')->assertOk()->assertDontSee('Certificate owner')->assertDontSee('Second secret owner')->assertDontSee('000123-4')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_exact_lookup_preserves_full_slug_zeros_and_suffix(): void
    {
        $this->certificate('000123-4');
        foreach (['123-4', '000123', '000123-', '000123-40', 'Certificate owner'] as $slug) {
            $this->post('/certificate-search', ['slug' => $slug])->assertNotFound()->assertDontSee('Certificate owner');
        }
        $this->post('/certificate-search', ['slug' => '000123-4'])->assertStatus(303)->assertRedirect(url('/000123-4/'));
        $this->get('/000123-4/')->assertOk()->assertSee('Certificate owner');
    }

    public function test_raw_whitespace_and_case_are_not_normalized(): void
    {
        $this->certificate('CERT-0001');
        $this->post('/certificate-search', ['slug' => 'cert-0001'])->assertNotFound();
        $this->call('POST', '/certificate-search', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], 'slug=CERT-0001%20')->assertNotFound();
        $this->get('/CERT-0001/')->assertOk();
    }

    public function test_anonymous_root_and_image_preserve_original_bytes(): void
    {
        $certificate = $this->certificate();
        $this->get('/269501-2/')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $url = URL::temporarySignedRoute('certificates.image', now()->addMinutes(10), ['slug' => $certificate->slug]);
        $response = $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(Storage::disk('local')->get($certificate->image), $response->streamedContent());
        $this->get('/certificate-media/269501-2')->assertForbidden();
        $this->get('/storage/'.$certificate->image)->assertForbidden();
        $this->get('/certificate-assets/certificates.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }

    public function test_signed_images_cannot_be_used_to_enumerate_other_slugs(): void
    {
        $this->certificate();
        $this->certificate('269501-3', 'Other owner');
        $url = URL::temporarySignedRoute('certificates.image', now()->addMinutes(10), ['slug' => '269501-2']);
        $this->get(str_replace('269501-2', '269501-3', $url))->assertForbidden();
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_search_and_root_guesses_share_thirty_attempts_per_ip(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->post('/certificate-search', ['slug' => 'nonexistent'])->assertNotFound();
            $this->get('/999999-9/')->assertNotFound();
        }
        $this->get('/999999-9/')->assertStatus(429)->assertHeader('Retry-After')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])->get('/999999-9/')->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->travel(61)->seconds();
        $this->get('/999999-9/')->assertNotFound();
    }

    public function test_successful_redirect_counts_once_and_grant_is_single_use(): void
    {
        $this->certificate();
        for ($i = 0; $i < 29; $i++) {
            $this->get('/999999-9/')->assertNotFound();
        }
        $this->post('/certificate-search', ['slug' => '269501-2'])->assertStatus(303);
        $this->get('/269501-2/')->assertOk();
        $this->get('/269501-2/')->assertStatus(429);
    }

    public function test_image_requests_do_not_use_lookup_budget(): void
    {
        $this->certificate();
        $url = URL::temporarySignedRoute('certificates.image', now()->addMinutes(10), ['slug' => '269501-2']);
        for ($i = 0; $i < 31; $i++) {
            $this->get($url)->assertOk();
        }
        $this->get('/269501-2/')->assertOk();
    }

    public function test_only_admin_and_super_admin_can_list_even_if_staff_has_permissions(): void
    {
        $this->certificate();
        $this->get('/admin/plugins/certificates')->assertRedirect('/login');
        foreach (['admin', 'super-admin'] as $role) {
            $this->actingAs($this->administrator($role))->get('/admin/plugins/certificates')->assertOk()->assertSee('Certificate owner');
        }
        $staff = $this->administrator('staff');
        $staff->givePermissionTo(json_decode(file_get_contents(dirname(__DIR__).'/module.json'), true)['permissions']);
        $this->actingAs($staff)->get('/admin/plugins/certificates')->assertForbidden()->assertDontSee('Certificate owner');
        $menu = app(MenuManager::class)->getAdminMenu($staff);
        $this->assertStringNotContainsString('admin.plugins.certificates.index', json_encode($menu));
    }

    public function test_create_and_edit_all_fields_remove_old_image_and_old_slug(): void
    {
        $this->actingAs($this->administrator());
        $this->get('/admin/plugins/certificates/create')->assertOk();
        $original = UploadedFile::fake()->image('original.jpg', 2000, 1414);
        $originalBytes = file_get_contents($original->getRealPath());
        $this->post('/admin/plugins/certificates', ['name' => 'First name', 'date' => '2026-10-03', 'slug' => 'ABC-0002', 'image' => $original])->assertSessionHasNoErrors()->assertRedirect();
        $certificate = Certificate::findExact('ABC-0002');
        $this->assertNotNull($certificate);
        $oldImage = $certificate->image;
        $this->assertSame($originalBytes, Storage::disk('local')->get($oldImage));
        $new = UploadedFile::fake()->image('replacement.png', 2000, 1414);
        $newBytes = file_get_contents($new->getRealPath());
        $this->put('/admin/plugins/certificates/'.$certificate->id, ['name' => 'Updated name', 'date' => '2026-10-04', 'slug' => 'NEW-0002', 'image' => $new])->assertSessionHasNoErrors()->assertRedirect();
        $updated = $certificate->fresh();
        $this->assertSame('Updated name', $updated->name);
        $this->assertSame('2026-10-04', $updated->date->toDateString());
        $this->assertSame('NEW-0002', $updated->slug);
        $this->assertFalse(Storage::disk('local')->exists($oldImage));
        $this->assertSame($newBytes, Storage::disk('local')->get($updated->image));
        $this->get('/ABC-0002/')->assertNotFound();
        $this->get('/NEW-0002/')->assertOk()->assertSee('Updated name');
    }

    public function test_edit_without_upload_keeps_the_image(): void
    {
        $certificate = $this->certificate();
        $this->actingAs($this->administrator())->put('/admin/plugins/certificates/'.$certificate->id, ['name' => 'Edited', 'date' => '2026-10-04', 'slug' => '000269501-2'])->assertSessionHasNoErrors();
        $this->assertSame($certificate->image, $certificate->fresh()->image);
        $this->get('/269501-2/')->assertNotFound();
        $this->get('/000269501-2/')->assertOk();
    }

    public function test_duplicate_and_platform_conflicts_are_rejected_without_renaming(): void
    {
        $this->certificate();
        $this->actingAs($this->administrator());
        Route::get('/reserved-page', static fn () => response('other plugin'))->name('another.public');
        foreach (['269501-2', 'login', 'reserved-page', 'bad/slug'] as $slug) {
            $this->post('/admin/plugins/certificates', ['name' => 'Bad', 'date' => '2026-10-03', 'slug' => $slug, 'image' => UploadedFile::fake()->image('cert.jpg')])->assertSessionHasErrors('slug');
        }
        $this->assertSame(1, Certificate::count());
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->administrator())->post('/admin/plugins/certificates', ['name' => 'Bad', 'date' => '2026-10-03', 'slug' => '111111', 'image' => UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('image');
        $this->assertSame(0, Certificate::count());
    }

    public function test_edit_invalidates_cached_slug_pattern(): void
    {
        $certificate = $this->certificate('old-custom-slug');
        // Simulate an existing route-cache artifact without booting a second
        // application with a different in-memory SQLite connection.
        File::put($this->app->getCachedRoutesPath(), '<?php return [];');
        $this->assertTrue($this->app->routesAreCached());
        $certificate->update(['slug' => 'new-custom-slug']);
        $this->assertFalse($this->app->routesAreCached());
        $this->get('/old-custom-slug/')->assertNotFound();
        $this->get('/new-custom-slug/')->assertOk();
    }

    public function test_disabling_plugin_preserves_data_and_blocks_public_and_admin(): void
    {
        $this->certificate();
        app(PluginManager::class)->deactivate('certificates');
        $this->get('/269501-2/')->assertNotFound();
        $this->actingAs($this->administrator())->get('/admin/plugins/certificates')->assertNotFound();
        $this->assertSame(1, Certificate::count());
    }
}
