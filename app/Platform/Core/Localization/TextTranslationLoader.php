<?php

namespace App\Platform\Core\Localization;

use Illuminate\Contracts\Foundation\Application;

/**
 * Loads text language files.
 *
 * Core:    lang/{locale}/core.txt               -> .account.title = core.account.title
 * Modules: modules/{slug}/lang/{locale}/core.txt -> courses.title  = <slug>::courses.title
 *
 * Format:  key = "value"
 *
 * This loader is the single source of truth for the `core` group: it runs on
 * boot and registers every key, which shadows a legacy lang/{locale}/core.php
 * (Laravel never re-reads a group that is already loaded). Keeping one source
 * per key is what makes the translations screen deterministic.
 */
final readonly class TextTranslationLoader
{
    public function __construct(private Application $app) {}

    public function register(): void
    {
        $this->scan(true);
    }

    /**
     * Read every text language file without touching the translator.
     *
     * @return array{
     *     locales: list<string>,
     *     modules: list<string>,
     *     files: list<array{locale:string,namespace:null|string,path:string,keys:int,items:list<string>,duplicates:list<string>,malformed:list<array{line:int,text:string}>}>
     * }
     */
    public function diagnostics(): array
    {
        return $this->scan(false);
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

    /** @return list<string> */
    public function modules(): array
    {
        $modules = [];

        foreach (glob(base_path('modules/*/lang'), GLOB_ONLYDIR) ?: [] as $directory) {
            $slug = basename(dirname($directory));

            if (preg_match('/^[a-z0-9_\-]+$/', $slug) === 1) {
                $modules[] = $slug;
            }
        }

        sort($modules);

        return $modules;
    }

    /**
     * @return array{
     *     locales: list<string>,
     *     modules: list<string>,
     *     files: list<array{locale:string,namespace:null|string,path:string,keys:int,items:list<string>,duplicates:list<string>,malformed:list<array{line:int,text:string}>}>
     * }
     */
    private function scan(bool $register): array
    {
        $translator = $register ? $this->app['translator'] : null;
        $locales = $this->locales();
        $modules = $this->modules();
        $files = [];

        foreach ($locales as $locale) {
            $path = lang_path($locale.'/core.txt');

            if (! is_file($path)) {
                continue;
            }

            $stats = ['duplicates' => [], 'malformed' => []];
            $lines = $this->parse((string) file_get_contents($path), true, $stats);

            if ($register && $lines !== []) {
                $translator->addLines($lines, $locale);
            }

            $files[] = [
                'locale' => $locale,
                'namespace' => null,
                'path' => $path,
                'keys' => count($lines),
                'items' => array_keys($lines),
                'duplicates' => $stats['duplicates'],
                'malformed' => $stats['malformed'],
            ];
        }

        foreach ($modules as $module) {
            foreach ($locales as $locale) {
                $path = base_path('modules/'.$module.'/lang/'.$locale.'/core.txt');

                if (! is_file($path)) {
                    continue;
                }

                $stats = ['duplicates' => [], 'malformed' => []];
                $lines = $this->parse((string) file_get_contents($path), false, $stats);

                if ($register && $lines !== []) {
                    $translator->addLines($lines, $locale, $module);
                }

                $files[] = [
                    'locale' => $locale,
                    'namespace' => $module,
                    'path' => $path,
                    'keys' => count($lines),
                    'items' => array_keys($lines),
                    'duplicates' => $stats['duplicates'],
                    'malformed' => $stats['malformed'],
                ];
            }
        }

        return ['locales' => $locales, 'modules' => $modules, 'files' => $files];
    }

    /**
     * @param  array{duplicates:list<string>,malformed:list<array{line:int,text:string}>}  $stats
     * @return array<string, string>
     */
    private function parse(string $contents, bool $core, array &$stats): array
    {
        $lines = [];
        $number = 0;

        foreach ($this->rawLines($contents) as $line) {
            $number++;

            [$key, $value] = $this->split($line);

            if ($key === null) {
                continue;
            }

            if ($core && ! str_starts_with($key, '.')) {
                $stats['malformed'][] = ['line' => $number, 'text' => 'key must start with a dot: '.$key];

                continue;
            }

            $item = ltrim($key, '.');

            if ($item === '' || preg_match('/^[A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)*$/', $item) !== 1) {
                $stats['malformed'][] = ['line' => $number, 'text' => 'invalid key: '.$key];

                continue;
            }

            if ($value === '') {
                $stats['malformed'][] = ['line' => $number, 'text' => 'empty value: '.$key];

                continue;
            }

            $resolved = $core ? 'core.'.$item : $item;

            if (array_key_exists($resolved, $lines)) {
                $stats['duplicates'][] = $resolved;
            }

            $lines[$resolved] = $value;
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
