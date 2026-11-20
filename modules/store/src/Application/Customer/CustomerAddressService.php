<?php

namespace Modules\Store\Application\Customer;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class CustomerAddressService
{
    public function __construct(
        private PublicIdGenerator $ids,
        private TransactionManager $transactions,
    ) {}

    public function list(int $userId): Collection
    {
        return DB::table('store_shipping_addresses')
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
    }

    public function find(int $userId, string $publicId): object
    {
        $address = DB::table('store_shipping_addresses')
            ->where('user_id', $userId)
            ->where('public_id', $publicId)
            ->first();

        return $address ?? throw new CustomerResourceNotFound('Shipping address does not exist.');
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): string
    {
        return $this->transactions->run(function () use ($userId, $data): string {
            $publicId = (string) $this->ids->generate();
            $makeDefault = (bool) ($data['is_default'] ?? false)
                || ! DB::table('store_shipping_addresses')->where('user_id', $userId)->exists();
            if ($makeDefault) {
                DB::table('store_shipping_addresses')->where('user_id', $userId)->update(['is_default' => false]);
            }

            DB::table('store_shipping_addresses')->insert($this->payload($data) + [
                'public_id' => $publicId,
                'user_id' => $userId,
                'is_default' => $makeDefault,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $publicId;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $userId, string $publicId, array $data): void
    {
        $this->transactions->run(function () use ($userId, $publicId, $data): void {
            $address = $this->find($userId, $publicId);
            $makeDefault = (bool) ($data['is_default'] ?? false);
            if ($makeDefault) {
                DB::table('store_shipping_addresses')->where('user_id', $userId)->update(['is_default' => false]);
            }

            DB::table('store_shipping_addresses')->where('id', $address->id)->update(
                $this->payload($data) + [
                    'is_default' => $makeDefault || (bool) $address->is_default,
                    'updated_at' => now(),
                ],
            );
        });
    }

    public function setDefault(int $userId, string $publicId): void
    {
        $this->transactions->run(function () use ($userId, $publicId): void {
            $address = $this->find($userId, $publicId);
            DB::table('store_shipping_addresses')->where('user_id', $userId)->update(['is_default' => false]);
            DB::table('store_shipping_addresses')->where('id', $address->id)->update([
                'is_default' => true,
                'updated_at' => now(),
            ]);
        });
    }

    public function delete(int $userId, string $publicId): void
    {
        $this->transactions->run(function () use ($userId, $publicId): void {
            $address = $this->find($userId, $publicId);
            DB::table('store_shipping_addresses')->where('id', $address->id)->delete();
            if ((bool) $address->is_default) {
                $next = DB::table('store_shipping_addresses')->where('user_id', $userId)->orderBy('created_at')->first();
                if ($next !== null) {
                    DB::table('store_shipping_addresses')->where('id', $next->id)->update([
                        'is_default' => true,
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function payload(array $data): array
    {
        $nullable = static fn (mixed $value): ?string => trim((string) $value) === '' ? null : trim((string) $value);

        return [
            'label' => trim((string) ($data['label'] ?? 'Home')),
            'recipient_name' => trim((string) $data['recipient_name']),
            'phone' => trim((string) $data['phone']),
            'country_code' => strtoupper((string) ($data['country_code'] ?? 'JO')),
            'governorate' => $nullable($data['governorate'] ?? null),
            'city' => trim((string) $data['city']),
            'area' => $nullable($data['area'] ?? null),
            'district' => null,
            'street' => trim((string) $data['address_line_1']),
            'building' => null,
            'floor' => null,
            'apartment' => null,
            'postal_code' => $nullable($data['postal_code'] ?? null),
            'landmark' => null,
            'delivery_notes' => null,
        ];
    }
}
