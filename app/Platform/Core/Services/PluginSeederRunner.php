<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\Artisan;

class PluginSeederRunner
{
    public function run(PluginManifest $manifest): void
    {
        foreach ($this->seeders($manifest) as $seeder) {
            Artisan::call('db:seed', [
                '--class' => $seeder,
                '--force' => true,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function seeders(PluginManifest $manifest): array
    {
        $seeders = data_get($manifest->manifest, 'install.seeders')
            ?: data_get($manifest->manifest, 'seeders')
            ?: [];

        if (is_string($seeders)) {
            $seeders = [$seeders];
        }

        if (! is_array($seeders)) {
            return [];
        }

        return array_values(array_filter($seeders, 'is_string'));
    }
}
