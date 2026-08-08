<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'group_key',
        'setting_key',
        'label',
        'type',
        'value',
        'default_value',
        'options',
        'help_text',
        'sort_order',
        'is_public',
        'validation_rules',
        'description',
        'category',
        'module',
        'visibility_level',
        'admin_access_level',
        'editable',
        'required',
        'sensitive_flag',
        'public_exposure_allowed',
        'frontend_available',
        'cache_enabled',
        'cache_ttl',
        'ui_component',
        'ui_label',
        'allowed_values',
        'min_value',
        'max_value',
        'unit',
        'depends_on',
        'restart_required',
        'approval_required',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'default_value' => 'json',
            'options' => 'json',
            'is_public' => 'boolean',
            'validation_rules' => 'json',
            'editable' => 'boolean',
            'required' => 'boolean',
            'sensitive_flag' => 'boolean',
            'public_exposure_allowed' => 'boolean',
            'frontend_available' => 'boolean',
            'cache_enabled' => 'boolean',
            'allowed_values' => 'json',
            'depends_on' => 'json',
            'restart_required' => 'boolean',
            'approval_required' => 'boolean',
        ];
    }
}
