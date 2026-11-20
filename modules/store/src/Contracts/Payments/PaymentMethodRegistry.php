<?php

namespace Modules\Store\Contracts\Payments;

interface PaymentMethodRegistry
{
    public function register(PaymentMethod $method): void;

    public function has(string $code): bool;

    public function get(string $code): ?PaymentMethod;

    public function default(): PaymentMethod;

    /** @return array<string, PaymentMethod> */
    public function all(): array;
}
