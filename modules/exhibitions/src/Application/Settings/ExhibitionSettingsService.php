<?php

namespace Modules\Exhibition\Application\Settings;

use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;

final class ExhibitionSettingsService
{
    /** @var array<string, mixed> */
    private array $resolvedValues = [];

    private ?bool $settingsTable = null;

    public function __construct(
        private AuditRecorder $audit,
        private CoreUserGateway $users,
        private PluginRepository $plugins,
    ) {}

    /** @return array{groups:array<string,array<string,mixed>>,definitions:array<string,array<string,mixed>>} */
    public function catalogue(): array
    {
        $catalogue = (array) config('exhibitions.settings', ['groups' => [], 'definitions' => []]);
        $persisted = Schema::hasTable('exhibition_settings')
            ? DB::table('exhibition_settings')->where('key', 'like', 'exhibitions.%')->get()->keyBy('key')
            : collect();

        foreach ($catalogue['definitions'] as $key => &$definition) {
            $row = $persisted->get($key);
            $definition['key'] = $key;
            $definition['current'] = $row ? json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR) : $definition['default'];
            $definition['version'] = (int) ($row?->version ?? 0);
            $definition['last_saved_at'] = $row?->updated_at;
            $catalogue['groups'][$definition['group']]['fields'][$key] = $definition;
        }
        unset($definition);

        return $catalogue;
    }

    public function value(string $key, mixed $fallback = null): mixed
    {
        if (! str_starts_with($key, 'exhibitions.')) {
            throw new \InvalidArgumentException('Exhibitions settings must use the exhibitions.* namespace.');
        }

        if (array_key_exists($key, $this->resolvedValues)) {
            return $this->resolvedValues[$key];
        }

        $this->settingsTable ??= Schema::hasTable('exhibition_settings');

        if ($this->settingsTable) {
            $stored = DB::table('exhibition_settings')->where('key', $key)->value('value');
            if ($stored !== null) {
                return $this->resolvedValues[$key] = json_decode((string) $stored, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        $definition = $this->definitions()[$key] ?? null;

        return $this->resolvedValues[$key] = is_array($definition) ? $definition['default'] : $fallback;
    }

    /** @param array<string,mixed> $values */
    public function save(int $actorUserId, array $values, string $reason, bool $currencyChangeConfirmed = false): int
    {
        $reason = $this->reason($reason);
        $definitions = $this->definitions();
        $normalized = [];
        foreach ($values as $key => $value) {
            if (! str_starts_with((string) $key, 'exhibitions.') || ! isset($definitions[$key])) {
                throw ValidationException::withMessages(['settings' => "Unknown or non-namespaced Exhibitions setting: $key"]);
            }
            $normalized[$key] = $this->normalize($key, $definitions[$key], $value);
        }
        $this->validateRelationships($normalized);
        $this->guardCurrency($normalized, $currencyChangeConfirmed);

        return DB::transaction(function () use ($actorUserId, $reason, $definitions, $normalized): int {
            $changed = 0;
            foreach ($normalized as $key => $value) {
                $definition = $definitions[$key];
                $current = DB::table('exhibition_settings')->where('key', $key)->lockForUpdate()->first();
                $previous = $current ? json_decode((string) $current->value, true, 512, JSON_THROW_ON_ERROR) : $definition['default'];
                if ($previous === $value) {
                    continue;
                }
                if (($definition['locked'] ?? false) === true) {
                    throw ValidationException::withMessages(["settings.$key" => 'This server-enforced setting cannot be changed.']);
                }
                $version = ((int) ($current?->version ?? 0)) + 1;
                $now = now();
                $attributes = [
                    'group' => $definition['group'],
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'version' => $version,
                    'updated_by' => $actorUserId,
                    'created_at' => $current?->created_at ?? $now,
                    'updated_at' => $now,
                ];
                if (Schema::hasColumn('exhibition_settings', 'type')) {
                    $attributes['type'] = $definition['type'];
                }
                if (Schema::hasColumn('exhibition_settings', 'is_encrypted')) {
                    $attributes['is_encrypted'] = false;
                }
                DB::table('exhibition_settings')->updateOrInsert(['key' => $key], $attributes);
                DB::table('exhibition_setting_history')->insert([
                    'setting_key' => $key,
                    'version' => $version,
                    'previous_value' => json_encode($previous, JSON_THROW_ON_ERROR),
                    'new_value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'actor_user_id' => $actorUserId,
                    'reason' => $reason,
                    'created_at' => $now,
                ]);
                $this->audit->record(new AuditEntryData(
                    action: 'exhibitions.setting.updated',
                    targetType: 'exhibition_setting',
                    targetId: $key,
                    previousValues: ['value' => $previous],
                    newValues: ['value' => $value, 'version' => $version, 'type' => $definition['type']],
                    reason: $reason,
                    context: ['namespace' => 'exhibitions', 'group' => $definition['group']],
                    actorUserId: $actorUserId,
                ));
                $changed++;
            }

            $this->resolvedValues = [];

            return $changed;
        });
    }

    public function resetGroup(int $actorUserId, string $group, string $reason): int
    {
        $groups = (array) config('exhibitions.settings.groups', []);
        if (! isset($groups[$group])) {
            throw ValidationException::withMessages(['group' => 'Unknown Exhibitions settings section.']);
        }
        $values = [];
        foreach ($this->definitions() as $key => $definition) {
            if ($definition['group'] === $group) {
                $values[$key] = $definition['default'];
            }
        }

        return $this->save($actorUserId, $values, $reason, true);
    }

    public function purgeSettings(int $actorUserId, string $confirmation, string $reason): int
    {
        if ($confirmation !== 'PURGE EXHIBITIONS') {
            throw ValidationException::withMessages(['confirmation' => 'Type PURGE EXHIBITIONS exactly.']);
        }
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actorUserId, $reason): int {
            $count = DB::table('exhibition_settings')->where('key', 'like', 'exhibitions.%')->count();
            DB::table('exhibition_setting_history')->where('setting_key', 'like', 'exhibitions.%')->delete();
            DB::table('exhibition_settings')->where('key', 'like', 'exhibitions.%')->delete();
            $this->audit->record(new AuditEntryData(
                action: 'exhibitions.settings.purged',
                targetType: 'module_settings',
                targetId: 'exhibitions',
                previousValues: ['setting_count' => $count],
                newValues: ['setting_count' => 0],
                reason: $reason,
                context: ['store_tables_touched' => false, 'orders_touched' => false],
                actorUserId: $actorUserId,
            ));

            return $count;
        });
    }

    public function history(int $limit = 50, ?string $group = null): object
    {
        $query = DB::table('exhibition_setting_history as history')
            ->where('history.setting_key', 'like', 'exhibitions.%');

        if ($group !== null) {
            $keys = collect($this->definitions())
                ->filter(fn (array $definition): bool => ($definition['group'] ?? null) === $group)
                ->keys()
                ->all();
            $query->whereIn('history.setting_key', $keys);
        }

        return $query
            ->latest('history.id')
            ->limit(min(200, max(1, $limit)))
            ->get(['history.*'])
            ->each(function (object $history): void {
                $history->actor_name = $history->actor_user_id
                    ? $this->users->find((int) $history->actor_user_id)?->name
                    : null;
            });
    }

    /** @return array<string,array{label:string,value:string,tone:string}> */
    public function integrationStatus(): array
    {
        try {
            $store = $this->plugins->findBySlug('store');
        } catch (\Throwable) {
            $store = null;
        }
        $externalContract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway';
        $feedContract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableLifecycleFeed';
        $lastSuccess = Schema::hasTable('exhibition_artwork_publication_events')
            ? DB::table('exhibition_artwork_publication_events')->where('state', 'published')->max('occurred_at')
            : null;
        $lastFailure = Schema::hasTable('exhibition_artwork_publication_events')
            ? DB::table('exhibition_artwork_publication_events')->where('state', 'sync_failed')->max('occurred_at')
            : null;
        $pending = Schema::hasTable('exhibition_artwork_publications')
            ? DB::table('exhibition_artwork_publications')->whereIn('state', ['pending', 'sync_failed'])->count()
            : 0;

        return [
            'store_module' => ['label' => 'Store module status', 'value' => $store?->status ?? 'unavailable', 'tone' => ($store?->status ?? null) === 'active' ? 'good' : 'warning'],
            'sellable_gateway' => ['label' => 'External sellable gateway status', 'value' => interface_exists($externalContract) && app()->bound($externalContract) ? 'available' : 'unavailable', 'tone' => interface_exists($externalContract) && app()->bound($externalContract) ? 'good' : 'warning'],
            'lifecycle_feed' => ['label' => 'Lifecycle feed status', 'value' => interface_exists($feedContract) && app()->bound($feedContract) ? 'available' : 'event-driven', 'tone' => 'good'],
            'last_success' => ['label' => 'Last successful sync', 'value' => $lastSuccess ?: 'Never', 'tone' => $lastSuccess ? 'good' : 'neutral'],
            'last_failure' => ['label' => 'Last failed sync', 'value' => $lastFailure ?: 'None', 'tone' => $lastFailure ? 'warning' : 'good'],
            'pending' => ['label' => 'Pending sync operations', 'value' => (string) $pending, 'tone' => $pending > 0 ? 'warning' : 'good'],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function definitions(): array
    {
        return (array) config('exhibitions.settings.definitions', []);
    }

    private function normalize(string $key, array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'boolean' => $this->boolean($value, $key),
            'integer' => $this->integer($value, $definition, $key),
            'decimal' => $this->decimal($value, $definition, $key),
            'json' => $this->json($value, $definition, $key),
            'string' => $this->string($value, $definition, $key),
            default => throw ValidationException::withMessages(["settings.$key" => 'Unsupported setting type.']),
        };
    }

    private function boolean(mixed $value, string $key): bool
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            throw ValidationException::withMessages(["settings.$key" => 'The value must be true or false.']);
        }

        return $normalized;
    }

    private function integer(mixed $value, array $definition, string $key): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(["settings.$key" => 'The value must be a whole number.']);
        }
        $number = (int) $value;
        if ($number < ($definition['min'] ?? PHP_INT_MIN) || $number > ($definition['max'] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages(["settings.$key" => 'The value is outside the allowed range.']);
        }

        return $number;
    }

    private function decimal(mixed $value, array $definition, string $key): string
    {
        $value = trim((string) $value);
        $scale = (int) ($definition['scale'] ?? 3);
        if (preg_match('/^\d+(?:\.\d{1,'.$scale.'})?$/', $value) !== 1) {
            throw ValidationException::withMessages(["settings.$key" => "Use a non-negative decimal with at most $scale decimal places."]);
        }
        $normalized = $this->fixedDecimal($value, $scale);
        if ($this->scaledInteger($normalized, $scale) < $this->scaledInteger((string) ($definition['min'] ?? '0'), $scale)
            || $this->scaledInteger($normalized, $scale) > $this->scaledInteger((string) ($definition['max'] ?? '999999999'), $scale)) {
            throw ValidationException::withMessages(["settings.$key" => 'The decimal value is outside the allowed range.']);
        }

        return $normalized;
    }

    private function json(mixed $value, array $definition, string $key): array
    {
        if (! is_array($value)) {
            $text = trim((string) $value);
            $decoded = str_starts_with($text, '[') || str_starts_with($text, '{') ? json_decode($text, true) : null;
            $value = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $text)), static fn (string $item): bool => $item !== ''));
        }
        if ($value === []) {
            throw ValidationException::withMessages(["settings.$key" => 'At least one value is required.']);
        }
        $value = array_values(array_unique(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
        foreach ((array) ($definition['locked_items'] ?? []) as $required) {
            if (! in_array($required, $value, true)) {
                throw ValidationException::withMessages(["settings.$key" => "Required workflow state cannot be removed: $required"]);
            }
        }

        return $value;
    }

    private function string(mixed $value, array $definition, string $key): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > 2000) {
            throw ValidationException::withMessages(["settings.$key" => 'Enter a value no longer than 2,000 characters.']);
        }
        if (isset($definition['options']) && ! in_array($value, $definition['options'], true)) {
            throw ValidationException::withMessages(["settings.$key" => 'Select an approved value.']);
        }
        if (isset($definition['pattern']) && preg_match($definition['pattern'], $value) !== 1) {
            throw ValidationException::withMessages(["settings.$key" => 'The value format is invalid.']);
        }

        return $value;
    }

    /** @param array<string,mixed> $values */
    private function validateRelationships(array $values): void
    {
        $current = fn (string $key): mixed => $values[$key] ?? $this->value($key);
        if ((int) $current('exhibitions.artist_applications.portfolio_images_min') > (int) $current('exhibitions.artist_applications.portfolio_images_max')) {
            throw ValidationException::withMessages(['settings' => 'Minimum portfolio images cannot exceed the maximum.']);
        }
        $minimum = $this->scaledInteger((string) $current('exhibitions.commission.minimum_rate'), 3);
        $default = $this->scaledInteger((string) $current('exhibitions.commission.default_rate'), 3);
        $maximum = $this->scaledInteger((string) $current('exhibitions.commission.maximum_rate'), 3);
        if ($minimum > $default || $default > $maximum) {
            throw ValidationException::withMessages(['settings' => 'Default commission must remain between the minimum and maximum rates.']);
        }
    }

    /** @param array<string,mixed> $values */
    private function guardCurrency(array $values, bool $confirmed): void
    {
        $key = 'exhibitions.currency.default';
        if (! array_key_exists($key, $values) || $values[$key] === $this->value($key) || $confirmed) {
            return;
        }
        $active = (Schema::hasTable('exhibition_settlements') && DB::table('exhibition_settlements')->whereNotIn('state', ['paid', 'reversed'])->exists())
            || (Schema::hasTable('exhibition_artwork_publications') && DB::table('exhibition_artwork_publications')->whereIn('state', ['published', 'pending', 'sync_failed'])->exists());
        if ($active) {
            throw ValidationException::withMessages([$key => 'Active financial activity exists. Confirm the currency change after reviewing its prospective-only impact.']);
        }
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'A reason between 8 and 1,000 characters is required.']);
        }

        return $reason;
    }

    private function fixedDecimal(string $value, int $scale): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ltrim($whole, '0') === '' ? '0.'.str_pad(substr($fraction, 0, $scale), $scale, '0') : ltrim($whole, '0').'.'.str_pad(substr($fraction, 0, $scale), $scale, '0');
    }

    private function scaledInteger(string $value, int $scale): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * (10 ** $scale)) + (int) str_pad(substr($fraction, 0, $scale), $scale, '0');
    }
}
