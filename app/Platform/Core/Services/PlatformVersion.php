<?php

namespace App\Platform\Core\Services;

final class PlatformVersion
{
    public function current(): string
    {
        return (string) config('platform.version', '2.0.0');
    }

    public function pluginApi(): string
    {
        return (string) config('platform.plugin_api', '2.0');
    }

    public function supports(?string $constraint): bool
    {
        $constraint = trim((string) $constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $requirements = preg_split('/\s*,\s*|\s+(?=[<>=~^])/', $constraint) ?: [];

        foreach ($requirements as $requirement) {
            if (! $this->matches(trim($requirement))) {
                return false;
            }
        }

        return true;
    }

    private function matches(string $requirement): bool
    {
        if ($requirement === '') {
            return true;
        }

        if (str_starts_with($requirement, '^')) {
            $minimum = substr($requirement, 1);
            $major = (int) explode('.', $minimum)[0];

            return version_compare($this->current(), $minimum, '>=')
                && version_compare($this->current(), ($major + 1).'.0.0', '<');
        }

        if (preg_match('/^(>=|<=|>|<|=|==)?\s*(\d+(?:\.\d+){0,2}(?:[-+][0-9A-Za-z.-]+)?)$/', $requirement, $matches) !== 1) {
            return false;
        }

        return version_compare($this->current(), $matches[2], $matches[1] ?: '==');
    }
}
