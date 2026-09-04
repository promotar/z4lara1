<?php

namespace App\Platform\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SettingsDefinitionSynchronizer
{
    /** @var list<string> */
    private const JSON_COLUMNS = [
        'default_value', 'options', 'validation_rules', 'allowed_values', 'depends_on',
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function synchronize(): int
    {
        if (! Schema::hasTable('platform_settings')) {
            return 0;
        }

        $availableColumns = array_fill_keys(Schema::getColumnListing('platform_settings'), true);
        $now = now();
        $rows = [];

        foreach ($this->settings->definitions() as $groupKey => $fields) {
            foreach ($fields as $settingKey => $definition) {
                $isPublic = (bool) ($definition['is_public'] ?? false);
                $row = [
                    'group_key' => $groupKey,
                    'setting_key' => $settingKey,
                    'label' => $definition['label'],
                    'type' => $definition['type'],
                    'default_value' => $definition['default'],
                    'options' => $definition['options'] ?? null,
                    'help_text' => $definition['help_text'] ?? null,
                    'sort_order' => $definition['sort_order'] ?? 0,
                    'is_public' => $isPublic,
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
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $row = array_intersect_key($row, $availableColumns);

                foreach (self::JSON_COLUMNS as $column) {
                    if (array_key_exists($column, $row)) {
                        $row[$column] = json_encode($row[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }

                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return 0;
        }

        $updateColumns = array_values(array_diff(array_keys($rows[0]), [
            'group_key', 'setting_key', 'value', 'created_at',
        ]));

        DB::table('platform_settings')->upsert($rows, ['group_key', 'setting_key'], $updateColumns);

        $this->settings->importLegacyJsonValues();
        $this->settings->forgetMemoizedValues();

        return count($rows);
    }
}
