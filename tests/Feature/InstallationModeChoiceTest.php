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

    public function test_fresh_deployment_enters_the_installation_wizard_immediately(): void
    {
        $this->get(route('install.index'))
            ->assertRedirect(route('install.platform'))
            ->assertSessionHas('installation.mode', 'fresh');

        $this->get(route('install.platform'))
            ->assertOk()
            ->assertSee('Platform identity')
            ->assertDontSee('Update platform');
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

    public function test_unsafe_manual_update_switch_is_not_exposed(): void
    {
        $this->post('/install/update')->assertNotFound();
    }
}
