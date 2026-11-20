<?php

namespace Modules\Store\Application\Catalog;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;

final readonly class ProductPolicyService
{
    public function __construct(
        private ConnectionInterface $database,
        private Clock $clock,
    ) {}

    public function save(
        string $productPublicId,
        ?string $shippingMode,
        ?string $shippingPolicy,
        ?string $returnMode,
        ?string $returnPolicy,
        ActorContext $actor,
    ): void {
        $values = [];
        if ($shippingMode !== null) {
            $values['shipping_policy'] = $this->policy($shippingMode, $shippingPolicy, 'Shipping');
        }
        if ($returnMode !== null) {
            $values['return_policy'] = $this->policy($returnMode, $returnPolicy, 'Return');
        }
        if ($values === []) {
            return;
        }
        $values['updated_by'] = $actor->type === 'user' ? (int) $actor->id : null;
        $values['updated_at'] = $this->clock->now();
        $updated = $this->database->table('store_products')
            ->where('public_id', $productPublicId)
            ->update($values);

        if ($updated === 0 && ! $this->database->table('store_products')->where('public_id', $productPublicId)->exists()) {
            throw new ProductNotFound('Product was not found.');
        }
    }

    private function policy(string $mode, ?string $policy, string $label): ?string
    {
        if ($mode === 'inherit') {
            return null;
        }
        if ($mode !== 'custom') {
            throw new InvalidArgumentException($label.' policy source is invalid.');
        }

        $policy = trim((string) $policy);
        if ($policy === '') {
            throw new InvalidArgumentException($label.' policy is required when a custom policy is selected.');
        }
        if (mb_strlen($policy) > 20_000) {
            throw new InvalidArgumentException($label.' policy is too long.');
        }

        return $policy;
    }
}
