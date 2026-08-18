<?php

namespace App\Platform\Core\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class PlatformMailConfigurator
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Apply the database-backed platform mail settings to Laravel's mail manager.
     * When SMTP is disabled, mail is written to the application log and no
     * outbound SMTP connection is attempted.
     */
    public function apply(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $environmentUsername = config('mail.mailers.smtp.username');
        $environmentPassword = config('mail.mailers.smtp.password');
        $values = $this->settings->values();
        $enabled = filter_var($values['mail.smtp_enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $previousMailer = (string) config('mail.default', 'log');
        $mailer = $enabled ? 'smtp' : 'log';

        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $this->scheme((string) ($values['mail.smtp_encryption'] ?? 'tls')),
            'mail.mailers.smtp.host' => trim((string) ($values['mail.smtp_host'] ?? '127.0.0.1')),
            'mail.mailers.smtp.port' => (int) ($values['mail.smtp_port'] ?? 587),
            'mail.mailers.smtp.username' => $this->nullableString($values['mail.smtp_username'] ?? null) ?? $environmentUsername,
            'mail.mailers.smtp.password' => $this->nullableString($values['mail.smtp_password'] ?? null) ?? $environmentPassword,
            'mail.mailers.smtp.timeout' => max(1, min(300, (int) ($values['mail.smtp_timeout'] ?? 30))),
            'mail.mailers.smtp.auto_tls' => ($values['mail.smtp_encryption'] ?? 'tls') !== 'none',
            'mail.from.address' => trim((string) ($values['mail.from_address'] ?? config('mail.from.address'))),
            'mail.from.name' => trim((string) ($values['mail.from_name'] ?? config('app.name'))),
        ]);

        Mail::purge($previousMailer);
        if ($previousMailer !== $mailer) {
            Mail::purge($mailer);
        }
    }

    private function scheme(string $encryption): ?string
    {
        return match ($encryption) {
            'smtps' => 'smtps',
            'none' => 'smtp',
            default => null,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
