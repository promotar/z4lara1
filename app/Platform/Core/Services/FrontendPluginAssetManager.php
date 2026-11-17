<?php

namespace App\Platform\Core\Services;

final class FrontendPluginAssetManager
{
    public function __construct(private readonly PluginAssetRegistry $registry) {}

    /** @return list<array<string, string>> */
    public function styles(): array
    {
        return $this->registry->styles('frontend');
    }

    /** @return list<array<string, string>> */
    public function scripts(): array
    {
        return $this->registry->scripts('frontend');
    }

    /**
     * @return array{styles: list<array<string, string>>, scripts: list<array<string, string>>}
     */
    public function assets(): array
    {
        return $this->registry->assets('frontend');
    }
}
