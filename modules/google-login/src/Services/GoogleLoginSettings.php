<?php

namespace Modules\GoogleLogin\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\GoogleLogin\Models\GoogleLoginSetting;
use Throwable;

class GoogleLoginSettings
{
    /** @return array<string, mixed> */
    public function publicValues(): array
    {
        $row = $this->row();

        return [
            'enabled' => (bool) $row?->enabled,
            'allow_registration' => $row?->allow_registration ?? true,
            'client_id' => trim((string) $row?->client_id),
            'hosted_domain' => trim((string) $row?->hosted_domain),
            'button_text' => $this->normalizeButtonText((string) $row?->button_text),
            'has_client_secret' => $this->clientSecret() !== null,
            'configured' => $this->configured(),
        ];
    }

    public function available(): bool
    {
        $row = $this->row();

        return (bool) $row?->enabled && $this->configured();
    }

    public function configured(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    public function clientId(): ?string
    {
        $value = trim((string) $this->row()?->client_id);

        return $value !== '' ? $value : null;
    }

    public function clientSecret(): ?string
    {
        $encrypted = trim((string) $this->row()?->client_secret_encrypted);
        if ($encrypted === '') {
            return null;
        }

        try {
            $secret = trim(Crypt::decryptString($encrypted));

            return $secret !== '' ? $secret : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function hostedDomain(): ?string
    {
        $value = strtolower(trim((string) $this->row()?->hosted_domain));

        return $value !== '' ? $value : null;
    }

    public function allowRegistration(): bool
    {
        return $this->row()?->allow_registration ?? true;
    }

    public function buttonText(): string
    {
        return $this->normalizeButtonText((string) $this->row()?->button_text);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): void
    {
        $row = $this->row(create: true);
        if (! $row) {
            throw ValidationException::withMessages(['settings' => 'Google Login settings storage is not available.']);
        }

        $clearSecret = (bool) ($values['clear_client_secret'] ?? false);
        $newSecret = trim((string) ($values['client_secret'] ?? ''));
        $encryptedSecret = $row->client_secret_encrypted;

        if ($clearSecret) {
            $encryptedSecret = null;
        } elseif ($newSecret !== '') {
            $encryptedSecret = Crypt::encryptString($newSecret);
        }

        $enabled = (bool) ($values['enabled'] ?? false);
        $clientId = trim((string) ($values['client_id'] ?? ''));

        if ($enabled && ($clientId === '' || empty($encryptedSecret))) {
            throw ValidationException::withMessages([
                'enabled' => 'Add both the Google Client ID and Client Secret before enabling Google Login.',
            ]);
        }

        $row->forceFill([
            'enabled' => $enabled,
            'allow_registration' => (bool) ($values['allow_registration'] ?? false),
            'client_id' => $clientId !== '' ? $clientId : null,
            'client_secret_encrypted' => $encryptedSecret,
            'hosted_domain' => ($domain = strtolower(trim((string) ($values['hosted_domain'] ?? '')))) !== '' ? $domain : null,
            'button_text' => $this->normalizeButtonText((string) ($values['button_text'] ?? '')),
        ])->save();
    }

    private function row(bool $create = false): ?GoogleLoginSetting
    {
        if (! Schema::hasTable('google_login_settings')) {
            return null;
        }

        if ($create) {
            return GoogleLoginSetting::query()->firstOrCreate(['id' => 1], [
                'enabled' => false,
                'allow_registration' => true,
                'button_text' => 'Continue with Google',
            ]);
        }

        return GoogleLoginSetting::query()->find(1) ?? GoogleLoginSetting::query()->first();
    }

    private function normalizeButtonText(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? mb_substr($value, 0, 80) : 'Continue with Google';
    }
}
