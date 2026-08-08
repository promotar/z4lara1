<?php

namespace App\Platform\Core\Services;

final class AdminPluginAssetManager
{
    public function __construct(private readonly PluginAssetRegistry $registry) {}

    /** @return list<array<string, string>> */
    public function styles(): array
    {
        return $this->registry->styles('admin');
    }

    /** @return list<array<string, string>> */
    public function scripts(): array
    {
        return $this->registry->scripts('admin');
    }

    /**
     * @return array{styles: list<array<string, string>>, scripts: list<array<string, string>>}
     */
    public function assets(): array
    {
        return $this->registry->assets('admin');
    }
}
