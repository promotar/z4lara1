<?php

namespace Modules\Store\Application\Shipping;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Store\Domain\Shared\Exceptions\ConcurrencyConflict;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ShippingZoneManagementService
{
    private const LOCATION_TYPES = ['country', 'governorate'];

    public function __construct(
        private ConnectionInterface $database,
        private TransactionManager $transactions,
        private PublicIdGenerator $ids,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $userId): string
    {
        $validated = $this->validated($data);
        $publicId = (string) $this->ids->generate();
        $this->transactions->run(function () use ($validated, $publicId, $userId): void {
            $this->assertRestOfWorldAvailable($validated['locations']);
            $now = now();
            $zoneId = (int) $this->database->table('store_shipping_zones')->insertGetId([
                'public_id' => $publicId,
                'name' => $validated['name'],
                'method_code' => 'delivery',
                'delivery_cost_minor' => $validated['delivery_cost_minor'],
                'free_shipping_threshold_minor' => $validated['free_shipping_threshold_minor'],
                'estimated_delivery' => $validated['estimated_delivery'],
                'customer_message' => $validated['customer_message'],
                'status' => $validated['active'] ? 'active' : 'inactive',
                'priority' => $validated['priority'],
                'created_by' => $userId,
                'updated_by' => $userId,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->replaceLocations($zoneId, $validated['locations'], $now);
        });

        return $publicId;
    }

    /** @param array<string, mixed> $data */
    public function update(string $publicId, int $expectedVersion, array $data, int $userId): void
    {
        $validated = $this->validated($data);
        $this->transactions->run(function () use ($publicId, $expectedVersion, $validated, $userId): void {
            $zone = $this->database->table('store_shipping_zones')
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->first();
            if ($zone === null) {
                throw new InvalidArgumentException('Shipping zone does not exist.');
            }
            if ((int) $zone->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Shipping zone version is stale.');
            }
            $this->assertRestOfWorldAvailable($validated['locations'], (int) $zone->id);
            $now = now();
            $updated = $this->database->table('store_shipping_zones')
                ->where('id', $zone->id)
                ->where('version', $expectedVersion)
                ->update([
                    'name' => $validated['name'],
                    'delivery_cost_minor' => $validated['delivery_cost_minor'],
                    'free_shipping_threshold_minor' => $validated['free_shipping_threshold_minor'],
                    'estimated_delivery' => $validated['estimated_delivery'],
                    'customer_message' => $validated['customer_message'],
                    'status' => $validated['active'] ? 'active' : 'inactive',
                    'priority' => $validated['priority'],
                    'updated_by' => $userId,
                    'version' => $this->database->raw('version + 1'),
                    'updated_at' => $now,
                ]);
            if ($updated !== 1) {
                throw new ConcurrencyConflict('Shipping zone update lost a concurrency race.');
            }
            $this->replaceLocations((int) $zone->id, $validated['locations'], $now);
        });
    }

    public function setActive(string $publicId, int $expectedVersion, bool $active, int $userId): void
    {
        $updated = $this->database->table('store_shipping_zones')
            ->where('public_id', $publicId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $active ? 'active' : 'inactive',
                'updated_by' => $userId,
                'version' => $this->database->raw('version + 1'),
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new ConcurrencyConflict('Shipping zone status changed before your request was saved.');
        }
    }

    public function delete(string $publicId, int $expectedVersion, string $confirmation): void
    {
        $this->transactions->run(function () use ($publicId, $expectedVersion, $confirmation): void {
            $zone = $this->database->table('store_shipping_zones')
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->first();
            if ($zone === null) {
                throw new InvalidArgumentException('Shipping zone does not exist.');
            }
            if ((int) $zone->version !== $expectedVersion) {
                throw new ConcurrencyConflict('Shipping zone changed before the delete request was confirmed.');
            }
            if (! hash_equals((string) $zone->name, trim($confirmation))) {
                throw new InvalidArgumentException('Shipping zone name confirmation does not match.');
            }

            $this->database->table('store_shipping_zone_locations')
                ->where('shipping_zone_id', $zone->id)
                ->delete();
            $deleted = $this->database->table('store_shipping_zones')
                ->where('id', $zone->id)
                ->where('version', $expectedVersion)
                ->delete();
            if ($deleted !== 1) {
                throw new ConcurrencyConflict('Shipping zone deletion lost a concurrency race.');
            }
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validated(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Shipping zone name is required.');
        }
        $locations = [];
        foreach (self::LOCATION_TYPES as $type) {
            foreach (($data['locations'][$type] ?? []) as $value) {
                $value = trim((string) $value);
                $normalized = mb_strtolower($value, 'UTF-8');
                if ($normalized !== '') {
                    $locations[$type.':'.$normalized] = [
                        'type' => $type,
                        'value' => $value,
                        'normalized' => $normalized,
                    ];
                }
            }
        }
        if ($locations === []) {
            throw new InvalidArgumentException('Shipping zone requires at least one geographic match.');
        }

        return [
            'name' => $name,
            'delivery_cost_minor' => max(0, (int) ($data['delivery_cost_minor'] ?? 0)),
            'free_shipping_threshold_minor' => isset($data['free_shipping_threshold_minor'])
                ? max(0, (int) $data['free_shipping_threshold_minor'])
                : null,
            'estimated_delivery' => $this->nullable($data['estimated_delivery'] ?? null),
            'customer_message' => $this->nullable($data['customer_message'] ?? null),
            'active' => (bool) ($data['active'] ?? false),
            'priority' => min(10_000, max(-10_000, (int) ($data['priority'] ?? 0))),
            'locations' => array_values($locations),
        ];
    }

    /** @param list<array{type: string, value: string, normalized: string}> $locations */
    private function replaceLocations(int $zoneId, array $locations, mixed $now): void
    {
        $this->database->table('store_shipping_zone_locations')->where('shipping_zone_id', $zoneId)->delete();
        $this->database->table('store_shipping_zone_locations')->insert(array_map(
            static fn (array $location): array => [
                'shipping_zone_id' => $zoneId,
                'match_type' => $location['type'],
                'match_value' => $location['value'],
                'match_value_normalized' => $location['normalized'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $locations,
        ));
    }

    /** @param list<array{type: string, value: string, normalized: string}> $locations */
    private function assertRestOfWorldAvailable(array $locations, ?int $exceptZoneId = null): void
    {
        if (! collect($locations)->contains(
            static fn (array $location): bool => $location['type'] === 'country' && $location['normalized'] === '*',
        )) {
            return;
        }

        $query = $this->database->table('store_shipping_zone_locations')
            ->where('match_type', 'country')
            ->where('match_value_normalized', '*');
        if ($exceptZoneId !== null) {
            $query->where('shipping_zone_id', '!=', $exceptZoneId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('A Rest of World shipping zone already exists.');
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
