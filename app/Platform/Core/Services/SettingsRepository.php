<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\Models\PlatformMediaMetadata;
use App\Platform\Core\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingsRepository
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return $this->definitionPayloads();
        }

        $this->syncDefinitions();
        $this->importLegacyJsonValues();

        $settings = PlatformSetting::query()
            ->orderBy('group_key')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('group_key');

        return collect($this->groups())
            ->map(function (array $group, string $groupKey) use ($settings): array {
                $fields = $settings->get($groupKey, collect())
                    ->mapWithKeys(fn (PlatformSetting $setting): array => [
                        $setting->setting_key => $this->fieldPayload($setting),
                    ])
                    ->all();

                return [
                    'label' => $group['label'],
                    'description' => $group['description'],
                    'fields' => $fields,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $removeFiles
     * @param  array<string, mixed>  $mediaSelections
     */
    public function update(
        array $input,
        array $files = [],
        array $removeFiles = [],
        array $mediaSelections = [],
        ?int $changedBy = null,
        string $source = 'admin.settings',
    ): void {
        $this->syncDefinitions();
        $changed = false;

        PlatformSetting::query()
            ->orderBy('group_key')
            ->orderBy('sort_order')
            ->get()
            ->each(function (PlatformSetting $setting) use ($input, $files, $removeFiles, $mediaSelections, $changedBy, $source, &$changed): void {
                $group = $setting->group_key;
                $key = $setting->setting_key;

                if ($setting->type === 'file') {
                    $file = $files[$group][$key] ?? null;
                    $remove = (bool) data_get($removeFiles, "{$group}.{$key}", false);

                    if ($remove) {
                        $this->deleteSettingFile($setting);
                        $changed = $this->saveSettingValue($setting, null, $changedBy, $source) || $changed;

                        return;
                    }

                    $selectedMedia = trim((string) data_get($mediaSelections, "{$group}.{$key}", ''));

                    if ($selectedMedia !== '' && $this->isPublicSettingsFile($selectedMedia)) {
                        $changed = $this->saveSettingValue($setting, $selectedMedia, $changedBy, $source) || $changed;

                        return;
                    }

                    if ($file instanceof UploadedFile) {
                        $this->deleteSettingFile($setting);
                        $stored = $file->store('settings', 'public');
                        $changed = $this->saveSettingValue($setting, '/storage/'.$stored, $changedBy, $source) || $changed;
                    }

                    return;
                }

                $raw = data_get($input, "{$group}.{$key}", null);
                if (! data_has($input, "{$group}.{$key}")) {
                    return;
                }

                if ((bool) ($setting->sensitive_flag ?? false) && trim((string) $raw) === '') {
                    return;
                }

                $changed = $this->saveSettingValue($setting, $this->normalizeValue($setting, $raw), $changedBy, $source) || $changed;
            });

        if ($changed) {
            $this->clearSettingsCaches();
        }
    }

    public function path(): string
    {
        return 'Database table: platform_settings';
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return $this->defaultValues();
        }

        $this->syncDefinitions();

        return PlatformSetting::query()
            ->get()
            ->mapWithKeys(fn (PlatformSetting $setting): array => [
                $setting->group_key.'.'.$setting->setting_key => $this->settingValue($setting),
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitionPayloads(): array
    {
        return collect($this->groups())
            ->map(function (array $group, string $groupKey): array {
                $fields = collect($this->definitions()[$groupKey] ?? [])
                    ->mapWithKeys(fn (array $definition, string $key): array => [
                        $key => [
                            'label' => $definition['label'],
                            'type' => $definition['type'],
                            'value' => $definition['default'],
                            'has_custom_value' => false,
                            'default' => $definition['default'],
                            'options' => $definition['options'] ?? [],
                            'help_text' => $definition['help_text'] ?? null,
                            'is_public' => $definition['is_public'] ?? false,
                            'min_value' => $definition['min_value'] ?? null,
                            'max_value' => $definition['max_value'] ?? null,
                            'unit' => $definition['unit'] ?? null,
                        ],
                    ])
                    ->all();

                return [
                    'label' => $group['label'],
                    'description' => $group['description'],
                    'fields' => $fields,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultValues(): array
    {
        $values = [];

        foreach ($this->definitions() as $groupKey => $fields) {
            foreach ($fields as $key => $definition) {
                $values[$groupKey.'.'.$key] = $definition['default'];
            }
        }

        return $values;
    }

    /**
     * @return array<int, array{url: string, name: string, modified_at: string}>
     */
    public function mediaLibrary(): array
    {
        $this->importLegacyMediaMetadata();
        $metadata = $this->mediaMetadata();

        return collect(Storage::disk('public')->files('settings'))
            ->filter(fn (string $path): bool => preg_match('/\.(png|jpe?g|webp|ico)$/i', $path) === 1)
            ->map(function (string $path) use ($metadata): array {
                $url = '/storage/'.$path;

                return [
                    'url' => $url,
                    'name' => basename($path),
                    'modified_at' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($path)),
                    'metadata' => $metadata[$url] ?? [
                        'alt_text' => '',
                        'title' => '',
                        'caption' => '',
                        'description' => '',
                    ],
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    /**
     * @param  array{alt_text?: string|null, title?: string|null, caption?: string|null, description?: string|null}  $metadata
     */
    public function updateMediaMetadata(string $url, array $metadata): void
    {
        if (! $this->isPublicSettingsFile($url)) {
            return;
        }

        if (! Schema::hasTable('platform_media_metadata')) {
            return;
        }

        PlatformMediaMetadata::query()->updateOrCreate(
            ['url' => $url],
            [
                'alt_text' => trim((string) ($metadata['alt_text'] ?? '')),
                'title' => trim((string) ($metadata['title'] ?? '')),
                'caption' => trim((string) ($metadata['caption'] ?? '')),
                'description' => trim((string) ($metadata['description'] ?? '')),
            ],
        );
    }

    /**
     * @return array<string, array{alt_text: string, title: string, caption: string, description: string}>
     */
    private function mediaMetadata(): array
    {
        if (! Schema::hasTable('platform_media_metadata')) {
            return [];
        }

        return PlatformMediaMetadata::query()
            ->get(['url', 'alt_text', 'title', 'caption', 'description'])
            ->mapWithKeys(fn (PlatformMediaMetadata $metadata): array => [
                $metadata->url => [
                    'alt_text' => $metadata->alt_text,
                    'title' => $metadata->title,
                    'caption' => $metadata->caption ?? '',
                    'description' => $metadata->description ?? '',
                ],
            ])
            ->all();
    }

    private function importLegacyMediaMetadata(): void
    {
        if (! Schema::hasTable('platform_media_metadata')) {
            return;
        }

        $legacyPath = storage_path('app/platform/media-metadata.json');

        if (! File::exists($legacyPath) || PlatformMediaMetadata::query()->exists()) {
            return;
        }

        $decoded = json_decode(File::get($legacyPath), true);

        if (! is_array($decoded)) {
            return;
        }

        foreach ($decoded as $url => $metadata) {
            if (! is_string($url) || ! is_array($metadata) || ! $this->isPublicSettingsFile($url)) {
                continue;
            }

            PlatformMediaMetadata::query()->updateOrCreate(
                ['url' => $url],
                [
                    'alt_text' => trim((string) ($metadata['alt_text'] ?? '')),
                    'title' => trim((string) ($metadata['title'] ?? '')),
                    'caption' => trim((string) ($metadata['caption'] ?? '')),
                    'description' => trim((string) ($metadata['description'] ?? '')),
                ],
            );
        }
    }

    private function fieldPayload(PlatformSetting $setting): array
    {
        $isSensitive = (bool) ($setting->sensitive_flag ?? false);

        return [
            'label' => $setting->label,
            'type' => $setting->type,
            'value' => $isSensitive ? '' : $this->settingValue($setting),
            'has_custom_value' => $setting->value !== null,
            'has_secret_value' => $isSensitive && $setting->value !== null && $this->settingValue($setting) !== '',
            'default' => $setting->default_value,
            'options' => $this->resolvedOptions($setting),
            'help_text' => $setting->help_text,
            'is_public' => $setting->is_public,
            'min_value' => $setting->min_value ?? null,
            'max_value' => $setting->max_value ?? null,
            'unit' => $setting->unit ?? null,
        ];
    }

    private function settingValue(PlatformSetting $setting): mixed
    {
        $value = $setting->value ?? $setting->default_value;
        if (! (bool) ($setting->sensitive_flag ?? false) || ! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Existing plaintext values remain readable and will be encrypted
            // the next time an administrator changes the sensitive setting.
            return $value;
        }
    }

    private function normalizeValue(PlatformSetting $setting, mixed $value): mixed
    {
        if ($setting->type === 'boolean') {
            return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) || $value === true;
        }

        if (in_array($setting->type, ['select', 'radio'], true)) {
            $value = is_string($value) ? trim($value) : (string) $value;
            $options = $this->resolvedOptions($setting);

            return array_key_exists($value, $options) ? $value : $setting->default_value;
        }

        if ($setting->type === 'color') {
            $value = is_string($value) ? trim($value) : (string) $value;

            return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtolower($value) : $setting->default_value;
        }

        return is_string($value) ? trim($value) : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    private function resolvedOptions(PlatformSetting $setting): array
    {
        if ($setting->setting_key === 'front_page') {
            return $this->frontPageOptions();
        }

        if ($setting->setting_key === 'default_user_role') {
            return $this->roleOptions();
        }

        return is_array($setting->options) ? $setting->options : [];
    }

    /**
     * @return array<string, string>
     */
    private function frontPageOptions(): array
    {
        $options = [
            'front.home' => 'Default storefront home',
        ];

        if (
            Schema::hasTable('platform_pages')
            && Schema::hasColumn('platform_pages', 'content_type')
            && Route::has('pages.show')
        ) {
            DB::table('platform_pages')
                ->where('content_type', 'page')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['title', 'slug', 'status'])
                ->each(function (object $page) use (&$options): void {
                    $status = $page->status === 'published' ? '' : ' (Draft)';
                    $options['platform-page:'.$page->slug] = 'Page: '.$page->title.$status;
                });
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        if (Schema::hasTable('roles')) {
            $roles = DB::table('roles')
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all();

            if ($roles !== []) {
                return $roles;
            }
        }

        return [
            'user' => 'User',
            'customer' => 'Customer',
        ];
    }

    private function syncDefinitions(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        foreach ($this->definitions() as $groupKey => $fields) {
            foreach ($fields as $key => $definition) {
                PlatformSetting::query()->updateOrCreate(
                    [
                        'group_key' => $groupKey,
                        'setting_key' => $key,
                    ],
                    array_merge([
                        'label' => $definition['label'],
                        'type' => $definition['type'],
                        'default_value' => $definition['default'],
                        'options' => $definition['options'] ?? null,
                        'help_text' => $definition['help_text'] ?? null,
                        'sort_order' => $definition['sort_order'] ?? 0,
                        'is_public' => $definition['is_public'] ?? false,
                    ], $this->registryPayload($groupKey, $definition)),
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function registryPayload(string $groupKey, array $definition): array
    {
        $isPublic = (bool) ($definition['is_public'] ?? false);
        $payload = [
            'validation_rules' => $definition['validation_rules'] ?? null,
            'description' => $definition['description'] ?? $definition['help_text'] ?? null,
            'category' => $definition['category'] ?? $groupKey,
            'module' => $definition['module'] ?? 'core',
            'visibility_level' => $definition['visibility_level'] ?? ($isPublic ? 'public' : 'admin'),
            'admin_access_level' => $definition['admin_access_level'] ?? 'manage_settings',
            'editable' => $definition['editable'] ?? true,
            'required' => $definition['required'] ?? false,
            'sensitive_flag' => $definition['sensitive_flag'] ?? false,
            'public_exposure_allowed' => $definition['public_exposure_allowed'] ?? $isPublic,
            'frontend_available' => $definition['frontend_available'] ?? $isPublic,
            'cache_enabled' => $definition['cache_enabled'] ?? true,
            'cache_ttl' => $definition['cache_ttl'] ?? null,
            'ui_component' => $definition['ui_component'] ?? $definition['type'],
            'ui_label' => $definition['ui_label'] ?? $definition['label'],
            'allowed_values' => $definition['allowed_values'] ?? $definition['options'] ?? null,
            'min_value' => $definition['min_value'] ?? null,
            'max_value' => $definition['max_value'] ?? null,
            'unit' => $definition['unit'] ?? null,
            'depends_on' => $definition['depends_on'] ?? null,
            'restart_required' => $definition['restart_required'] ?? false,
            'approval_required' => $definition['approval_required'] ?? false,
            'status' => $definition['status'] ?? 'active',
            'version' => $definition['version'] ?? 1,
        ];

        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn('platform_settings', $column))
            ->all();
    }

    private function saveSettingValue(PlatformSetting $setting, mixed $value, ?int $changedBy, string $source): bool
    {
        $oldValue = $this->settingValue($setting);

        if ($oldValue === $value) {
            return false;
        }

        $storedValue = (bool) ($setting->sensitive_flag ?? false)
            ? Crypt::encryptString((string) $value)
            : $value;
        $setting->forceFill(['value' => $storedValue])->save();
        $this->recordSettingChange($setting, $oldValue, $value, $changedBy, $source);

        return true;
    }

    private function recordSettingChange(PlatformSetting $setting, mixed $oldValue, mixed $newValue, ?int $changedBy, string $source): void
    {
        if (! Schema::hasTable('operation_logs')) {
            return;
        }

        $isSensitive = (bool) ($setting->sensitive_flag ?? false);
        $timestamp = now();

        DB::table('operation_logs')->insert([
            'operation_type' => 'platform.setting.update',
            'target_type' => 'platform-setting',
            'target_slug' => $setting->group_key.'.'.$setting->setting_key,
            'status' => 'success',
            'message' => 'Platform setting updated.',
            'context' => json_encode([
                'key' => $setting->group_key.'.'.$setting->setting_key,
                'old_value' => $isSensitive ? '[sensitive]' : $oldValue,
                'new_value' => $isSensitive ? '[sensitive]' : $newValue,
                'changed_by' => $changedBy,
                'timestamp' => $timestamp->toDateTimeString(),
                'source' => $source,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'started_at' => $timestamp,
            'finished_at' => $timestamp,
            'created_by' => $changedBy,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function clearSettingsCaches(): void
    {
        foreach (['cache:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // Cache clearing should not block a committed database setting update.
            }
        }
    }

    private function importLegacyJsonValues(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $legacyPath = storage_path('app/platform/settings.json');

        if (! File::exists($legacyPath) || PlatformSetting::query()->whereNotNull('value')->exists()) {
            return;
        }

        $decoded = json_decode(File::get($legacyPath), true);

        if (! is_array($decoded) || $decoded === []) {
            return;
        }

        foreach ($decoded as $group => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as $key => $value) {
                $setting = PlatformSetting::query()
                    ->where('group_key', $group)
                    ->where('setting_key', $key)
                    ->first();

                if ($setting) {
                    $setting->forceFill(['value' => $value])->save();
                }
            }
        }
    }

    private function deletePublicFile(string $url): void
    {
        if (! $this->isPublicSettingsFile($url)) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $url));
    }

    private function deleteSettingFile(PlatformSetting $setting): void
    {
        $value = (string) $setting->value;
        $default = (string) $setting->default_value;

        if ($value === '' || $value === $default) {
            return;
        }

        $this->deletePublicFile($value);
    }

    private function isPublicSettingsFile(string $url): bool
    {
        if (! str_starts_with($url, '/storage/settings/')) {
            return false;
        }

        return Storage::disk('public')->exists(str_replace('/storage/', '', $url));
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private function groups(): array
    {
        return [
            'general' => [
                'label' => 'General Settings',
                'description' => '',
            ],
            'mail' => [
                'label' => 'Email & SMTP',
                'description' => 'Central outbound email configuration used by verification, password reset, notifications, and all platform mail.',
            ],
            'seo' => [
                'label' => 'SEO Settings',
                'description' => 'Default SEO metadata used by public pages when a page-specific value is not available.',
            ],
            'front_page' => [
                'label' => 'Front Page',
                'description' => 'Controls which page is used as the public landing page.',
            ],
            'theme' => [
                'label' => 'Theme Settings',
                'description' => 'Controls public theme colors for day and night mode.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function definitions(): array
    {
        return [
            'general' => [
                'site_title' => ['label' => 'Site Title', 'type' => 'text', 'default' => 'Z4Rank', 'sort_order' => 10, 'is_public' => true],
                'tagline' => ['label' => 'Tagline', 'type' => 'text', 'default' => 'القمة لتصدر نتائج البحث', 'sort_order' => 20, 'is_public' => true],
                'site_logo' => ['label' => 'Site Logo', 'type' => 'file', 'default' => '/storage/settings/Ob6BqGzoNd4zjfHezzskEj03aMQ365pNi8gTeXe9.png', 'sort_order' => 25, 'help_text' => 'Upload a PNG, JPG, or WEBP logo image. Recommended width: 240 pixels or larger.', 'is_public' => true],
                'site_icon' => ['label' => 'Site Icon', 'type' => 'file', 'default' => '/storage/settings/Ob6BqGzoNd4zjfHezzskEj03aMQ365pNi8gTeXe9.png', 'sort_order' => 30, 'help_text' => 'Upload a square PNG, JPG, WEBP, or ICO image. Recommended size: 512 x 512 pixels.', 'is_public' => true],
                'wordpress_address_url' => ['label' => 'Application Address URL', 'type' => 'url', 'default' => (string) config('app.url', 'http://localhost'), 'sort_order' => 40, 'is_public' => true],
                'site_address_url' => ['label' => 'Site Address URL', 'type' => 'url', 'default' => (string) config('app.url', 'http://localhost'), 'sort_order' => 50, 'is_public' => true],
                'admin_email' => ['label' => 'Administration Email Address', 'type' => 'email', 'default' => 'admin@z4rank.com', 'sort_order' => 60],
                'membership_enabled' => ['label' => 'Membership', 'type' => 'boolean', 'default' => true, 'sort_order' => 70, 'help_text' => 'Allow anyone to register.'],
                'email_verification_required' => ['label' => 'Require Email Verification', 'type' => 'boolean', 'default' => true, 'sort_order' => 80, 'help_text' => 'Require newly registered users to verify their email address before accessing verified areas.'],
                'default_user_role' => ['label' => 'New User Default Role', 'type' => 'select', 'default' => 'user', 'sort_order' => 90],
                'site_language' => ['label' => 'Site Language', 'type' => 'select', 'default' => 'ar', 'sort_order' => 100, 'options' => ['ar' => 'Arabic', 'en' => 'English'], 'is_public' => true],
                'timezone' => ['label' => 'Timezone', 'type' => 'select', 'default' => 'Asia/Amman', 'sort_order' => 110, 'options' => ['Asia/Amman' => 'Asia/Amman', 'UTC' => 'UTC', 'Asia/Riyadh' => 'Asia/Riyadh', 'Europe/London' => 'Europe/London', 'America/New_York' => 'America/New_York']],
                'date_format' => ['label' => 'Date Format', 'type' => 'radio', 'default' => 'F j, Y', 'sort_order' => 120, 'options' => ['F j, Y' => 'June 23, 2026', 'Y-m-d' => '2026-06-23', 'm/d/Y' => '06/23/2026', 'd/m/Y' => '23/06/2026', 'd.m.Y' => '23.06.2026', 'custom' => 'Custom']],
                'custom_date_format' => ['label' => 'Custom Date Format', 'type' => 'text', 'default' => 'F j, Y', 'sort_order' => 130],
                'time_format' => ['label' => 'Time Format', 'type' => 'radio', 'default' => 'g:i a', 'sort_order' => 140, 'options' => ['g:i a' => '1:14 pm', 'g:i A' => '1:14 PM', 'H:i' => '13:14', 'custom' => 'Custom']],
                'custom_time_format' => ['label' => 'Custom Time Format', 'type' => 'text', 'default' => 'g:i a', 'sort_order' => 150],
                'week_starts_on' => ['label' => 'Week Starts On', 'type' => 'select', 'default' => 'monday', 'sort_order' => 160, 'options' => ['saturday' => 'Saturday', 'sunday' => 'Sunday', 'monday' => 'Monday']],
            ],
            'mail' => [
                'smtp_enabled' => ['label' => 'Enable SMTP', 'type' => 'boolean', 'default' => (string) config('mail.default', 'log') === 'smtp', 'sort_order' => 10, 'help_text' => 'When disabled, outgoing messages are written to the application log and no SMTP connection is made.'],
                'smtp_host' => ['label' => 'SMTP Host', 'type' => 'text', 'default' => (string) config('mail.mailers.smtp.host', '127.0.0.1'), 'sort_order' => 20],
                'smtp_port' => ['label' => 'SMTP Port', 'type' => 'number', 'default' => (int) config('mail.mailers.smtp.port', 587), 'sort_order' => 30],
                'smtp_encryption' => ['label' => 'Encryption', 'type' => 'select', 'default' => 'tls', 'sort_order' => 40, 'options' => ['tls' => 'STARTTLS / Automatic', 'smtps' => 'Implicit TLS', 'none' => 'None']],
                'smtp_username' => ['label' => 'SMTP Username', 'type' => 'text', 'default' => '', 'sort_order' => 50],
                'smtp_password' => ['label' => 'SMTP Password', 'type' => 'password', 'default' => '', 'sort_order' => 60, 'sensitive_flag' => true, 'cache_enabled' => false, 'help_text' => 'Stored encrypted. Leave blank to keep the current password.'],
                'smtp_timeout' => ['label' => 'Connection Timeout', 'type' => 'number', 'default' => 30, 'sort_order' => 70, 'min_value' => 1, 'max_value' => 300, 'unit' => 'seconds'],
                'from_address' => ['label' => 'From Email Address', 'type' => 'email', 'default' => (string) config('mail.from.address', 'hello@example.com'), 'sort_order' => 80],
                'from_name' => ['label' => 'From Name', 'type' => 'text', 'default' => (string) config('app.name', 'Art INPA'), 'sort_order' => 90],
            ],
            'seo' => [
                'seo_title' => ['label' => 'Default SEO Title', 'type' => 'text', 'default' => 'Z4Rank', 'sort_order' => 10, 'is_public' => true],
                'seo_description' => ['label' => 'Default Meta Description', 'type' => 'textarea', 'default' => 'Z4Rank modular platform.', 'sort_order' => 20, 'is_public' => true],
                'seo_keywords' => ['label' => 'Default Meta Keywords', 'type' => 'text', 'default' => 'z4rank, seo, platform', 'sort_order' => 30, 'is_public' => true],
                'robots_index' => ['label' => 'Allow Search Engines To Index', 'type' => 'boolean', 'default' => true, 'sort_order' => 40, 'is_public' => true],
                'robots_follow' => ['label' => 'Allow Search Engines To Follow Links', 'type' => 'boolean', 'default' => true, 'sort_order' => 50, 'is_public' => true],
                'open_graph_title' => ['label' => 'Open Graph Title', 'type' => 'text', 'default' => 'Z4Rank', 'sort_order' => 60, 'is_public' => true],
                'open_graph_description' => ['label' => 'Open Graph Description', 'type' => 'textarea', 'default' => 'Z4Rank modular platform.', 'sort_order' => 70, 'is_public' => true],
                'open_graph_image' => ['label' => 'Open Graph Image', 'type' => 'file', 'default' => null, 'sort_order' => 80, 'help_text' => 'Recommended image size: 1200 x 630 pixels.', 'is_public' => true],
            ],
            'front_page' => [
                'front_page_mode' => ['label' => 'Homepage Displays', 'type' => 'radio', 'default' => 'default', 'sort_order' => 10, 'options' => ['default' => 'Default application home', 'static' => 'A selected page'], 'is_public' => true],
                'front_page' => ['label' => 'Front Page', 'type' => 'select', 'default' => 'front.home', 'sort_order' => 20, 'help_text' => 'Published platform pages appear here automatically.', 'is_public' => true],
            ],
            'theme' => [
                'light_background' => ['label' => 'Light Background', 'type' => 'color', 'default' => '#ffffff', 'sort_order' => 10, 'is_public' => true],
                'light_surface' => ['label' => 'Light Surface', 'type' => 'color', 'default' => '#ffffff', 'sort_order' => 20, 'is_public' => true],
                'light_text' => ['label' => 'Light Text', 'type' => 'color', 'default' => '#111827', 'sort_order' => 30, 'is_public' => true],
                'light_muted_text' => ['label' => 'Light Muted Text', 'type' => 'color', 'default' => '#4b5563', 'sort_order' => 40, 'is_public' => true],
                'dark_background' => ['label' => 'Dark Background', 'type' => 'color', 'default' => '#0f172a', 'sort_order' => 50, 'is_public' => true],
                'dark_surface' => ['label' => 'Dark Surface', 'type' => 'color', 'default' => '#111827', 'sort_order' => 60, 'is_public' => true],
                'dark_text' => ['label' => 'Dark Text', 'type' => 'color', 'default' => '#f9fafb', 'sort_order' => 70, 'is_public' => true],
                'dark_muted_text' => ['label' => 'Dark Muted Text', 'type' => 'color', 'default' => '#cbd5e1', 'sort_order' => 80, 'is_public' => true],
                'accent_color' => ['label' => 'Accent Color', 'type' => 'color', 'default' => '#df0000', 'sort_order' => 90, 'is_public' => true],
            ],
        ];
    }
}
