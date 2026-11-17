<?php

namespace App\Platform\Core\Localization;

use Illuminate\Contracts\Foundation\Application;

/**
 * Loads text language files.
 *
 * Core:   lang/{locale}/core.txt       -> .account.title = core.account.title
 * Modules: modules/{slug}/lang/{locale}/core.txt -> courses.title = lms::courses.title
 *
 * Format: key = "value"
 */
final readonly class TextTranslationLoader
{
    public function __construct(private Application $app) {}

    public function register(): void
    {
        $translator = $this->app['translator'];

        foreach ($this->locales() as $locale) {
            $path = lang_path($locale.'/core.txt');

            if (is_file($path)) {
                $lines = $this->parseCore((string) file_get_contents($path));

                if ($lines !== []) {
                    $translator->addLines($lines, $locale);
                }
            }
        }

        foreach (glob(base_path('modules/*/lang'), GLOB_ONLYDIR) ?: [] as $directory) {
            $slug = basename(dirname($directory));

            if (preg_match('/^[a-z0-9_\-]+$/', $slug) !== 1) {
                continue;
            }

            foreach ($this->locales() as $locale) {
                $path = $directory.'/'.$locale.'/core.txt';

                if (! is_file($path)) {
                    continue;
                }

                $lines = $this->parseModule((string) file_get_contents($path));

                if ($lines !== []) {
                    $translator->addLines($lines, $locale, $slug);
                }
            }
        }
    }

    /** @return list<string> */
    public function locales(): array
    {
        $locales = [];

        foreach (glob(lang_path('*/core.txt')) ?: [] as $file) {
            $code = basename(dirname($file));

            if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code) === 1) {
                $locales[] = $code;
            }
        }

        sort($locales);

        return array_values(array_unique($locales));
    }

    /** @return array<string, string> */
    private function parseCore(string $contents): array
    {
        $lines = [];

        foreach ($this->rawLines($contents) as $line) {
            [$key, $value] = $this->split($line);

            if ($key === null || $value === null) {
                continue;
            }

            if (! str_starts_with($key, '.')) {
                continue;
            }

            $lines['core.'.ltrim($key, '.')] = $value;
        }

        return $lines;
    }

    /** @return array<string, string> */
    private function parseModule(string $contents): array
    {
        $lines = [];

        foreach ($this->rawLines($contents) as $line) {
            [$key, $value] = $this->split($line);

            if ($key === null || $value === null) {
                continue;
            }

            $lines[ltrim($key, '.')] = $value;
        }

        return $lines;
    }

    /** @return list<string> */
    private function rawLines(string $contents): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));
    }

    /** @return array{0:?string,1:?string} */
    private function split(string $line): array
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            return [null, null];
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = stripcslashes(substr($value, 1, -1));
        }

        return [$key, $value];
    }
}
