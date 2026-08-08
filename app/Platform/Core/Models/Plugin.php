<?php

namespace App\Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    use HasFactory;

    public const STATUS_INSTALLED = 'installed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'name',
        'slug',
        'version',
        'description',
        'author',
        'status',
        'path',
        'provider',
        'manifest',
        'dependencies',
        'installed_at',
        'activated_at',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'dependencies' => 'array',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function isCore(): bool
    {
        $manifest = is_array($this->manifest) ? $this->manifest : [];
        $type = strtolower(trim((string) data_get($manifest, 'type', '')));
        $core = data_get($manifest, 'core', data_get($manifest, 'lifecycle.core', false));

        return $type === 'core'
            || $core === true
            || (is_string($core) && in_array(strtolower(trim($core)), ['1', 'true', 'yes', 'on'], true))
            || (is_int($core) && $core === 1);
    }

    public function isAdminTheme(): bool
    {
        $manifest = is_array($this->manifest) ? $this->manifest : [];
        $type = strtolower(trim((string) data_get($manifest, 'type', '')));
        $scope = strtolower(trim((string) data_get($manifest, 'theme.scope', data_get($manifest, 'scope', ''))));

        return $type === 'theme' && $scope === 'admin';
    }

    public function isDefaultAdminTheme(): bool
    {
        $manifest = is_array($this->manifest) ? $this->manifest : [];
        $default = data_get($manifest, 'theme.default', false);

        return $this->slug === 'admin-theme'
            || $default === true
            || (is_string($default) && in_array(strtolower(trim($default)), ['1', 'true', 'yes', 'on'], true))
            || (is_int($default) && $default === 1);
    }

    /**
     * @param Builder<Plugin> $query
     * @return Builder<Plugin>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
