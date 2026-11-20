<?php

namespace Modules\Store\Events;

final readonly class StoreRuntimeRegistered
{
    public function __construct(public string $version) {}
}
