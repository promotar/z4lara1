<?php

namespace App\Platform\Core\Updates;

class VersionComparator
{
    public function compare(string $currentVersion, string $availableVersion): int
    {
        return version_compare($this->normalize($currentVersion), $this->normalize($availableVersion));
    }

    public function isUpdateAvailable(string $currentVersion, string $availableVersion): bool
    {
        return $this->compare($currentVersion, $availableVersion) < 0;
    }

    private function normalize(string $version): string
    {
        $version = trim($version);

        return $version === '' ? '0.0.0' : ltrim($version, 'vV');
    }
}
