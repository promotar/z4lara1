<?php

namespace Modules\Store\Domain\Orders;

use InvalidArgumentException;

final class OrderTransitionPolicy
{
    /** @return list<OrderStatus> */
    public function allowed(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::New => [
                OrderStatus::Confirmed,
                OrderStatus::Processing,
                OrderStatus::Ready,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
            ],
            OrderStatus::Confirmed => [
                OrderStatus::Processing,
                OrderStatus::Ready,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
            ],
            OrderStatus::Processing => [
                OrderStatus::Ready,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
            ],
            OrderStatus::Ready => [
                OrderStatus::Shipped,
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
            ],
            OrderStatus::Shipped => [OrderStatus::Delivered, OrderStatus::Cancelled],
            OrderStatus::Delivered => [OrderStatus::Returned],
            OrderStatus::Cancelled, OrderStatus::Returned => [],
        };
    }

    public function assertAllowed(OrderStatus $from, OrderStatus $to, bool $override = false, ?string $reason = null): void
    {
        if ($from === $to) {
            throw new InvalidArgumentException('The order is already in the requested status.');
        }

        if (in_array($to, $this->allowed($from), true)) {
            return;
        }

        if ($override
            && trim((string) $reason) !== ''
            && ! in_array($from, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return;
        }

        throw new InvalidArgumentException("Order cannot move from {$from->value} to {$to->value}.");
    }
}
