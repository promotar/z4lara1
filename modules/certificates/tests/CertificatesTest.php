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
        $this->get('/certificate-search')->assertOk()->assertDontSee('Certificate owner')->assertDontSee('Second secret owner')->assertDontSee('000123-4')->assertDontSee('269501-2')->assertDontSee('placeholder=', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
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

    public function test_public_skin_keeps_exact_lookup_and_only_one_noindex_directive(): void
    {
        $html = $this->get('/certificate-search')->assertOk()
            ->assertSee('أدخل رقم الشهادة كاملًا لعرض النسخة الأصلية، دون تسجيل دخول.')
            ->assertSee('aria-describedby="certificate-help"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('العودة إلى الصفحة الرئيسية')
            ->assertDontSee('placeholder=', false)
            ->assertDontSee('مثال توضيحي فقط')->getContent();
        $this->assertSame(1, substr_count($html, 'name="robots"'));
        $this->assertStringContainsString('content="noindex, nofollow, noarchive"', $html);
        $this->assertStringNotContainsString('property="og:title"', $html);
        $css = $this->get('/certificate-assets/certificates.css')->assertOk()->getContent();
        $this->assertStringContainsString('text-align:right;direction:ltr', $css);
        $this->assertStringContainsString('@media(max-width:575px)', $css);
    }

    public function test_public_layout_reuses_current_site_sections_without_copying_or_changing_them(): void
    {
        // Fixture data lives only in this test's guarded in-memory SQLite database.
        $header = DB::table('platform_pages')->insertGetId([
            'title' => 'Shared header', 'slug' => 'shared-header', 'content_type' => 'header',
            'status' => 'published', 'html' => '<header data-test-shared-header>Current site header</header>',
            'css' => '[data-test-shared-header]{color:#624397}',
        ]);
        $footer = DB::table('platform_pages')->insertGetId([
            'title' => 'Shared footer', 'slug' => 'shared-footer', 'content_type' => 'footer',
            'status' => 'published', 'html' => '<footer data-test-shared-footer>Current site footer</footer>',
            'css' => '[data-test-shared-footer]{background:#261b37}',
        ]);
        if (class_exists(\Modules\PageBuilder\ThemeCompositionService::class)) {
            if (! Schema::hasTable('vvvebjs_layout_sections')) {
                Schema::create('vvvebjs_layout_sections', function ($table): void {
                    $table->id();
                    $table->string('placement');
                    $table->unsignedBigInteger('page_id');
                    $table->integer('sort_order')->default(0);
                });
            }
            DB::table('vvvebjs_layout_sections')->insert([
                ['placement' => 'header', 'page_id' => $header, 'sort_order' => 0],
                ['placement' => 'footer', 'page_id' => $footer, 'sort_order' => 0],
            ]);
        }
        $before = DB::table('platform_pages')->orderBy('id')->get()->toJson();
        $this->get('/certificate-search')->assertOk()->assertSee('Current site header')->assertSee('Current site footer')
            ->assertSee('[data-test-shared-header]{color:#624397}', false);
        $this->assertSame($before, DB::table('platform_pages')->orderBy('id')->get()->toJson());
        DB::table('platform_pages')->where('id', $header)->update(['html' => '<header data-test-shared-header>Updated site header</header>']);
        $this->get('/certificate-search')->assertOk()->assertSee('Updated site header')->assertDontSee('Current site header');
        $this->certificate();
        $this->get('/269501-2/')->assertOk()->assertSee('Updated site header')->assertSee('Current site footer');
        $this->get('/987654-2/')->assertNotFound()->assertSee('Updated site header')->assertSee('Current site footer');
    }

    public function test_public_assets_are_local_allow_listed_and_do_not_consume_attempts(): void
    {
        foreach (['cairo-arabic.woff2' => 'font/woff2', 'cairo-latin.woff2' => 'font/woff2', 'certificate.svg' => 'image/svg+xml'] as $asset => $mime) {
            $path = str_ends_with($asset, '.woff2') ? 'fonts/'.$asset : 'icons/'.$asset;
            $response = $this->get('/certificate-assets/'.$asset)->assertOk()
                ->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame(file_get_contents(dirname(__DIR__).'/resources/assets/'.$path), $response->getContent());
        }
        foreach (['OFL.txt', 'LICENSE.txt', 'unknown.svg', '../module.json', 'fonts/cairo-arabic.woff2'] as $asset) {
            $this->get('/certificate-assets/'.$asset)->assertNotFound();
        }
        for ($i = 0; $i < 31; $i++) {
            $this->get('/certificate-assets/cairo-arabic.woff2')->assertOk();
        }
        $this->certificate();
        $this->get('/269501-2/')->assertOk();
    }

    public function test_raw_whitespace_and_case_are_not_normalized(): void
    {
        $this->certificate('CERT-0001');
        $this->post('/certificate-search', ['slug' => 'cert-0001'])->assertNotFound();
        $this->call('POST', '/certificate-search', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], 'slug=CERT-0001%20')->assertNotFound();
        $this->get('/CERT-0001/')->assertOk();
    }

    public function test_original_arabic_slug_round_trips_without_changing_its_url(): void
    {
        $slug = 'شهادة-اختبار-001';
        $certificate = $this->certificate($slug, 'مالك اختباري');
        $this->assertSame(url('/'.rawurlencode($slug).'/'), $certificate->publicUrl());
        $this->get('/'.rawurlencode($slug).'/')->assertOk()->assertSee('مالك اختباري');
        $this->post('/certificate-search', ['slug' => $slug])->assertStatus(303)->assertRedirect($certificate->publicUrl());
        $this->post('/certificate-search', ['slug' => 'شهادة-اختبار'])->assertNotFound();
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

    public function test_replayed_session_grant_cannot_bypass_attempt_limit(): void
    {
        $this->certificate();
        for ($i = 0; $i < 29; $i++) {
            $this->get('/999999-9/')->assertNotFound();
        }
        $this->post('/certificate-search', ['slug' => '269501-2'])->assertStatus(303);
        $nonce = session('certificates.view_grant');
        $this->get('/269501-2/')->assertOk();
        $this->withSession(['certificates.view_grant' => $nonce])->get('/269501-2/')->assertStatus(429);
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
        $this->actingAs($staff)->get('/admin/plugins/certificates?q=Certificate')->assertForbidden()->assertDontSee('Certificate owner');
        $menu = app(MenuManager::class)->getAdminMenu($staff);
        $this->assertStringNotContainsString('admin.plugins.certificates.index', json_encode($menu));
    }

    public function test_administrators_can_search_by_name_or_certificate_number(): void
    {
        $this->certificate('001234-2', 'أحمد محمود');
        $this->certificate('998877-3', 'سارة خالد');
        foreach (['admin', 'super-admin'] as $role) {
            $this->actingAs($this->administrator($role));
            foreach (['أحمد', 'أحمد محمود', '001234-2', '001234'] as $term) {
                $this->get('/admin/plugins/certificates?'.http_build_query(['q' => $term]))
                    ->assertOk()->assertSee('أحمد محمود')->assertDontSee('سارة خالد');
            }
            $this->get('/admin/plugins/certificates?q=not-found')->assertOk()->assertSee('لا توجد شهادات مطابقة.')->assertDontSee('أحمد محمود')->assertDontSee('سارة خالد');
            $this->get('/admin/plugins/certificates?q=')->assertOk()->assertSee('أحمد محمود')->assertSee('سارة خالد');
            $this->get('/admin/plugins/certificates?slug=001234-2')->assertOk()->assertSee('أحمد محمود')->assertDontSee('سارة خالد');
        }
        $this->post('/certificate-search', ['slug' => 'أحمد'])->assertNotFound()->assertDontSee('أحمد محمود');
        $this->post('/certificate-search', ['slug' => '001234'])->assertNotFound()->assertDontSee('أحمد محمود');
    }

    public function test_administrative_search_treats_wildcards_as_literal_text(): void
    {
        $this->certificate('123456', 'Literal %_! name');
        $this->certificate('654321', 'Another owner');
        $this->actingAs($this->administrator());
        foreach (['%', '_', '!', '%_!'] as $term) {
            $this->get('/admin/plugins/certificates?'.http_build_query(['q' => $term]))
                ->assertOk()->assertSee('Literal %_! name')->assertDontSee('Another owner');
        }
        $this->get('/admin/plugins/certificates?'.http_build_query(['q' => "' OR 1=1 --"]))
            ->assertOk()->assertSee('لا توجد شهادات مطابقة.')->assertDontSee('Another owner');
    }

    public function test_administrative_search_keeps_its_filter_in_pagination(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->certificate('PAGE-'.$i, 'Matching owner '.$i);
        }
        $this->certificate('OTHER-001', 'Other owner');
        $response = $this->actingAs($this->administrator())->get('/admin/plugins/certificates?q=Matching');
        $response->assertOk()->assertDontSee('Other owner');
        $this->assertSame(26, $response->viewData('certificates')->total());
        $this->assertStringContainsString('q=Matching', $response->viewData('certificates')->nextPageUrl());
        $this->get('/admin/plugins/certificates?q=Matching&page=2')->assertOk()->assertSee('Matching owner')->assertDontSee('Other owner');
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
        $this->app->forgetInstance('routes.cached');
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

    public function test_design_update_preserves_existing_records_and_original_images(): void
    {
        $certificate = $this->certificate('000123-4', 'Existing owner');
        $before = $certificate->fresh()->getAttributes();
        $original = Storage::disk('local')->get($certificate->image);
        DB::table('plugins')->where('slug', 'certificates')->update(['version' => '1.0.0']);
        $plugin = app(PluginManager::class)->update(dirname(__DIR__));
        $this->assertSame('1.0.1', $plugin->version);
        app(PluginManager::class)->activate($plugin);
        $this->assertSame(1, Certificate::count());
        $this->assertSame($before, $certificate->fresh()->getAttributes());
        $this->assertSame($original, Storage::disk('local')->get($certificate->image));
        $this->get('/000123-4/')->assertOk()->assertSee('Existing owner');
    }
}
