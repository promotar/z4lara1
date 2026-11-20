<?php

namespace Modules\Store\Application\Capabilities;

use Modules\Store\Contracts\Capabilities\StoreCapabilities;

final class ManifestStoreCapabilities implements StoreCapabilities
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(private readonly array $capabilities) {}

    public function has(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function all(): array
    {
        return $this->capabilities;
    }
}
