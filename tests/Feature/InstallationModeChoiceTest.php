<?php

namespace Tests\Feature;

use App\Installation\InstallationState;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class InstallationModeChoiceTest extends TestCase
{
    private string $stateDirectory;

    private string $runtimePath;

    private string $environmentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateDirectory = storage_path('framework/testing/installer-mode-choice');
        $this->runtimePath = $this->stateDirectory.'/installation.env';
        $this->environmentPath = $this->stateDirectory.'/.env';

        File::deleteDirectory($this->stateDirectory);
        File::ensureDirectoryExists($this->stateDirectory);
        File::put($this->runtimePath, "APP_KEY=\"test-key\"\nCUSTOM_KEEP=\"original-value\"\nINSTAAL_IS_ACTIVE=\"0\"\nINSTAAL_IS_ATIVE=\"0\"\n");
        File::put($this->environmentPath, "APP_NAME=\"Existing platform\"\nINSTAAL_IS_ACTIVE=\"0\"\nINSTAAL_IS_ATIVE=\"0\"\n");

        $this->app->instance(
            InstallationState::class,
            new InstallationState($this->runtimePath, $this->environmentPath),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->stateDirectory);

        parent::tearDown();
    }

    public function test_fresh_deployment_requires_an_explicit_installation_mode_choice(): void
    {
        $this->get(route('install.index'))
            ->assertOk()
            ->assertSee('Choose installation mode')
            ->assertSee('New installation')
            ->assertSee('Update existing installation')
            ->assertSessionMissing('installation.mode');
    }

    public function test_fresh_mode_opens_the_destructive_installation_wizard(): void
    {
        $this->post(route('install.mode'), ['mode' => 'fresh'])
            ->assertRedirect(route('install.platform'))
            ->assertSessionHas('installation.mode', 'fresh');

        $this->get(route('install.platform'))
            ->assertOk()
            ->assertSee('Platform identity')
            ->assertDontSee('Update without deleting data');
    }

    public function test_update_mode_opens_the_non_destructive_database_step(): void
    {
        $this->post(route('install.mode'), ['mode' => 'update'])
            ->assertRedirect(route('install.database'))
            ->assertSessionHas('installation.mode', 'update');

        $this->get(route('install.database'))
            ->assertOk()
            ->assertSee('Existing database connection')
            ->assertSee('Existing data is preserved')
            ->assertSee('Test connection and update')
            ->assertDontSee('I understand and authorize deleting all existing database tables');
    }

    public function test_forwarded_https_origin_is_prefilled_before_app_url_exists(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Host' => 'internal.test',
                'X-Forwarded-Host' => 'art.example.com',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->post('http://internal.test/install/mode', ['mode' => 'fresh'])
            ->assertRedirect('/install/platform')
            ->assertSessionHas('installation.runtime.app_url', 'https://art.example.com');

        $this->get(route('install.platform'))
            ->assertOk()
            ->assertSee('value="https://art.example.com"', false);
    }

    public function test_fresh_wizard_cannot_be_opened_before_selecting_fresh_installation(): void
    {
        $this->get(route('install.platform'))
            ->assertRedirect(route('install.index'));
    }

    public function test_installed_deployment_never_reopens_the_installer(): void
    {
        $this->app->make(InstallationState::class)->setInstalled(true);

        $this->get(route('install.index'))->assertRedirect('/');
        $this->get(route('install.platform'))->assertRedirect('/');
    }

    public function test_completion_marker_keeps_the_platform_installed_even_if_environment_flags_are_stale(): void
    {
        $state = $this->app->make(InstallationState::class);
        $state->setInstalled(true);

        $marker = $this->stateDirectory.'/installation.complete';
        $this->assertFileExists($marker);

        File::put($this->runtimePath, "INSTALLATION_COMPLETE=\"0\"\nINSTAAL_IS_ACTIVE=\"0\"\nINSTAAL_IS_ATIVE=\"0\"\n");
        File::put($this->environmentPath, "INSTALLATION_COMPLETE=\"0\"\nINSTAAL_IS_ACTIVE=\"0\"\n");

        $this->assertTrue($state->installed());
        $this->get(route('install.index'))->assertRedirect('/');
    }

    public function test_legacy_installed_flag_remains_authoritative_during_an_upgrade(): void
    {
        File::put($this->runtimePath, "INSTAAL_IS_ACTIVE=\"1\"\n");
        File::put($this->environmentPath, "INSTALLATION_COMPLETE=\"0\"\nINSTAAL_IS_ACTIVE=\"0\"\n");

        $state = $this->app->make(InstallationState::class);

        $this->assertTrue($state->installed());
        $this->get(route('install.index'))->assertRedirect('/');
    }

    public function test_unsafe_manual_update_switch_is_not_exposed(): void
    {
        $this->post('/install/update')->assertNotFound();
    }
}
