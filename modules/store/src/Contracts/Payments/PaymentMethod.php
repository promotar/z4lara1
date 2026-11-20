<?php

namespace Modules\Store\Contracts\Payments;

interface PaymentMethod
{
    public function code(): string;

    public function label(): string;

    public function isAvailable(): bool;
}
