<?php

namespace Modules\Store\Contracts\Capabilities;

interface StoreCapabilities
{
    public function has(string $capability): bool;

    /** @return list<string> */
    public function all(): array;
}
