<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    /** @var array<string, mixed> */
    private static array $memo = [];

    /** @var array<int, array<string, self>> */
    private static array $loaded = [];

    protected $table = 'seo_settings';

    protected $fillable = [
        'site_id',
        'module_slug',
        'group_key',
        'setting_key',
        'value',
        'type',
        'locale',
    ];

    public static function valueFor(
        int $siteId,
        string $moduleSlug,
        string $groupKey,
        string $settingKey,
        string $locale = '',
        mixed $default = null,
    ): mixed {
        $memoKey = implode('|', [$siteId, $moduleSlug, $groupKey, $settingKey, $locale]);

        if (array_key_exists($memoKey, self::$memo)) {
            return self::$memo[$memoKey];
        }

        if (! array_key_exists($siteId, self::$loaded)) {
            $loaded = [];

            foreach (static::query()->where('site_id', $siteId)->get() as $setting) {
                $loaded[implode('|', [
                    $setting->site_id,
                    $setting->module_slug,
                    $setting->group_key,
                    $setting->setting_key,
                    $setting->locale,
                ])] = $setting;
            }

            self::$loaded[$siteId] = $loaded;
        }

        $setting = self::$loaded[$siteId][$memoKey] ?? null;

        if ($setting === null) {
            return self::$memo[$memoKey] = $default;
        }

        $value = match ($setting->type) {
            'boolean' => in_array((string) $setting->value, ['1', 'true', 'on', 'yes'], true),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'json', 'array' => json_decode((string) $setting->value, true) ?? $default,
            default => $setting->value,
        };

        return self::$memo[$memoKey] = $value;
    }

    public static function flushCache(): void
    {
        self::$memo = [];
        self::$loaded = [];
    }

    public static function putValue(
        int $siteId,
        string $moduleSlug,
        string $groupKey,
        string $settingKey,
        mixed $value,
        string $type = 'string',
        string $locale = '',
    ): self {
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        self::flushCache();

        return static::query()->updateOrCreate(
            [
                'site_id' => $siteId,
                'module_slug' => $moduleSlug,
                'group_key' => $groupKey,
                'setting_key' => $settingKey,
                'locale' => $locale,
            ],
            [
                'value' => $stored,
                'type' => $type,
            ],
        );
    }
}
