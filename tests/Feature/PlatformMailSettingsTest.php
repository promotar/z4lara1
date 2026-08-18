<?php

namespace Tests\Feature;

use App\Platform\Core\Models\PlatformSetting;
use App\Platform\Core\Services\PlatformMailConfigurator;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_settings_are_applied_and_password_is_encrypted(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->update([
            'mail' => [
                'smtp_enabled' => true,
                'smtp_host' => 'smtp.example.test',
                'smtp_port' => 465,
                'smtp_encryption' => 'smtps',
                'smtp_username' => 'mailer@example.test',
                'smtp_password' => 'private-smtp-password',
                'smtp_timeout' => 45,
                'from_address' => 'noreply@example.test',
                'from_name' => 'Example Platform',
            ],
        ]);

        $stored = PlatformSetting::query()
            ->where('group_key', 'mail')
            ->where('setting_key', 'smtp_password')
            ->firstOrFail();

        $this->assertNotSame('private-smtp-password', $stored->value);
        $this->assertSame('private-smtp-password', $settings->values()['mail.smtp_password']);

        app(PlatformMailConfigurator::class)->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('private-smtp-password', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@example.test', config('mail.from.address'));
    }

    public function test_disabling_smtp_uses_log_mailer(): void
    {
        app(SettingsRepository::class)->update([
            'mail' => ['smtp_enabled' => false],
        ]);

        app(PlatformMailConfigurator::class)->apply();

        $this->assertSame('log', config('mail.default'));
    }
}
