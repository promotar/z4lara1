<?php

namespace Modules\GoogleLogin;

use App\Platform\Core\Contracts\UserDeletionParticipant as UserDeletionParticipantContract;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\GoogleLogin\Services\GoogleAccountManager;
use Modules\GoogleLogin\Services\GoogleLoginSettings;
use Modules\GoogleLogin\Services\GoogleLoginUserDeletionParticipant;
use Modules\GoogleLogin\Services\GoogleOAuthClient;
use Throwable;

class GoogleLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/Models/GoogleAccount.php';
        require_once __DIR__.'/Models/GoogleLoginSetting.php';
        require_once __DIR__.'/Services/GoogleLoginException.php';
        require_once __DIR__.'/Services/GoogleLoginSettings.php';
        require_once __DIR__.'/Services/GoogleOAuthClient.php';
        require_once __DIR__.'/Services/GoogleAccountManager.php';
        require_once __DIR__.'/Services/GoogleLoginUserDeletionParticipant.php';
        require_once __DIR__.'/Http/Controllers/Admin/GoogleLoginSettingsController.php';
        require_once __DIR__.'/Http/Controllers/Auth/GoogleLoginController.php';

        $this->app->singleton(GoogleLoginSettings::class);
        $this->app->singleton(GoogleOAuthClient::class);
        $this->app->singleton(GoogleAccountManager::class);
        $this->app->singleton(GoogleLoginUserDeletionParticipant::class);
        $this->app->tag(GoogleLoginUserDeletionParticipant::class, UserDeletionParticipantContract::class);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);

        View::addNamespace('google-login', $root.'/resources/views');

        /*
         * ACTIVE-PLUGIN LOGIN INTEGRATION BOUNDARY
         * ----------------------------------------
         * The core login view has no markup hook. This active provider prepends a
         * plugin-owned view location containing only auth/login.blade.php. When
         * this plugin is disabled its provider is no longer loaded, so Laravel
         * resolves the untouched core login view again automatically.
         */
        View::getFinder()->prependLocation($root.'/resources/views/overrides');

        View::composer('auth.login', function ($view): void {
            try {
                $settings = app(GoogleLoginSettings::class);
                $view->with([
                    'googleLoginAvailable' => $settings->available(),
                    'googleLoginButtonText' => $settings->buttonText(),
                ]);
            } catch (Throwable) {
                $view->with([
                    'googleLoginAvailable' => false,
                    'googleLoginButtonText' => 'Continue with Google',
                ]);
            }
        });
    }
}
