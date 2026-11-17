<?php

namespace App\Platform\Core\Plugins\Uninstall;

use App\Platform\Core\Services\PluginMenuRegistry;

class PluginMenuRemover
{
    public function __construct(
        private readonly PluginMenuRegistry $menus,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(string $slug): array
    {
        $existing = $this->menus->get($slug);
        $this->menus->unregister($slug);

        return [
            'removed' => $existing !== null,
        ];
    }
}
