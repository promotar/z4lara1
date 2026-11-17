<?php

namespace App\Platform\Core\Localization;

use Illuminate\Contracts\Foundation\Application;

/**
 * Registers every installed module's translation directory under its own
 * namespace, for example modules/lms/lang/ar/courses.php becomes
 * __('lms::courses.title').
 *
 * The core never imports module translation files and modules never write
 * into the core language directory.
 */
final readonly class ModuleTranslationLoader
{
    public function __construct(private Application $app) {}

    public function register(): void
    {
        $translator = $this->app['translator'];

        foreach ($this->directories('modules/*/lang') as $path) {
            $translator->addNamespace($this->slugFor($path), $path);
        }

        foreach ($this->directories('modules/*/resources/lang') as $path) {
            $translator->addNamespace($this->slugFor($path), $path);
        }
    }

    /** @return list<string> */
    private function directories(string $pattern): array
    {
        $matches = glob(base_path($pattern), GLOB_ONLYDIR) ?: [];

        return array_values(array_filter($matches, static fn (string $path): bool => is_dir($path) && is_readable($path)));
    }

    private function slugFor(string $path): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
        $index = array_search('modules', $segments, true);

        return $index !== false && isset($segments[$index + 1]) ? $segments[$index + 1] : basename(dirname($path));
    }
}
