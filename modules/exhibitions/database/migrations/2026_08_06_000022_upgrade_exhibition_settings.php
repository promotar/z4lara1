<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exhibition_settings')) {
            Schema::table('exhibition_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('exhibition_settings', 'type')) {
                    $table->string('type', 20)->default('string')->after('value');
                }
                if (! Schema::hasColumn('exhibition_settings', 'is_encrypted')) {
                    $table->boolean('is_encrypted')->default(false)->after('type');
                }
            });
            $this->namespaceLegacySettings();
            $this->seedDefaults();
        }

        if (Schema::hasTable('exhibition_artist_identities') && ! Schema::hasColumn('exhibition_artist_identities', 'commission_rate')) {
            Schema::table('exhibition_artist_identities', static function (Blueprint $table): void {
                $table->decimal('commission_rate', 6, 3)->nullable()->after('state');
            });
        }
        if (Schema::hasTable('exhibition_artworks') && ! Schema::hasColumn('exhibition_artworks', 'commission_rate')) {
            Schema::table('exhibition_artworks', static function (Blueprint $table): void {
                $table->decimal('commission_rate', 6, 3)->nullable()->after('currency');
            });
        }
        if (Schema::hasTable('exhibition_fulfillments') && ! Schema::hasColumn('exhibition_fulfillments', 'commission_basis_points_snapshot')) {
            Schema::table('exhibition_fulfillments', static function (Blueprint $table): void {
                $table->unsignedSmallInteger('commission_basis_points_snapshot')->nullable()->after('store_unit_number');
                $table->string('commission_source_snapshot', 32)->nullable()->after('commission_basis_points_snapshot');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exhibition_fulfillments') && Schema::hasColumn('exhibition_fulfillments', 'commission_basis_points_snapshot')) {
            Schema::table('exhibition_fulfillments', static function (Blueprint $table): void {
                $table->dropColumn(['commission_basis_points_snapshot', 'commission_source_snapshot']);
            });
        }
        if (Schema::hasTable('exhibition_artworks') && Schema::hasColumn('exhibition_artworks', 'commission_rate')) {
            Schema::table('exhibition_artworks', static fn (Blueprint $table) => $table->dropColumn('commission_rate'));
        }
        if (Schema::hasTable('exhibition_artist_identities') && Schema::hasColumn('exhibition_artist_identities', 'commission_rate')) {
            Schema::table('exhibition_artist_identities', static fn (Blueprint $table) => $table->dropColumn('commission_rate'));
        }
        if (Schema::hasTable('exhibition_settings')) {
            Schema::table('exhibition_settings', static function (Blueprint $table): void {
                $columns = array_values(array_filter(['type', 'is_encrypted'], static fn (string $column): bool => Schema::hasColumn('exhibition_settings', $column)));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    private function namespaceLegacySettings(): void
    {
        $aliases = [
            'return_window_days' => 'exhibitions.settlement.return_window_days',
        ];
        foreach (DB::table('exhibition_settings')->where('key', 'not like', 'exhibitions.%')->orderBy('id')->get() as $row) {
            $target = $aliases[$row->key] ?? 'exhibitions.legacy.'.$row->key;
            if ($row->key === 'commission_basis_points') {
                $target = 'exhibitions.commission.default_rate';
                $basisPoints = (int) json_decode((string) $row->value, true);
                $row->value = json_encode(number_format($basisPoints / 100, 3, '.', ''), JSON_THROW_ON_ERROR);
                $row->type = 'decimal';
            }
            if (DB::table('exhibition_settings')->where('key', $target)->exists()) {
                DB::table('exhibition_settings')->where('id', $row->id)->delete();
            } else {
                DB::table('exhibition_settings')->where('id', $row->id)->update([
                    'key' => $target,
                    'value' => $row->value,
                    'type' => $row->type ?? $this->inferType((string) $row->value),
                ]);
            }
            DB::table('exhibition_setting_history')->where('setting_key', $row->key)->update(['setting_key' => $target]);
        }
    }

    private function seedDefaults(): void
    {
        $settings = require dirname(__DIR__, 2).'/config/settings.php';
        $now = now();
        foreach ($settings['definitions'] as $key => $definition) {
            DB::table('exhibition_settings')->insertOrIgnore([
                'key' => $key,
                'group' => $definition['group'],
                'value' => json_encode($definition['default'], JSON_THROW_ON_ERROR),
                'type' => $definition['type'],
                'is_encrypted' => false,
                'version' => 1,
                'updated_by' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function inferType(string $json): string
    {
        $value = json_decode($json, true);

        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }
};
