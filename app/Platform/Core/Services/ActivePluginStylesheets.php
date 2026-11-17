<?php

namespace App\Platform\Core\Services;

final class ActivePluginStylesheets
{
    public function __construct(private readonly PluginAssetRegistry $registry) {}

    /** @return list<string> */
    public function files(): array
    {
        return $this->registry->sourceStyles();
    }
}
