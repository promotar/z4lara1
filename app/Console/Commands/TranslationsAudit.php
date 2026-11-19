<?php

namespace App\Console\Commands;

use App\Platform\Core\Localization\TextTranslationLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Automated validation for the platform translation layer.
 *
 * Checks:
 *   1. core.txt / module core.txt syntax (malformed lines, duplicates).
 *   2. Legacy lang/{locale}/core.php (shadowed by core.txt -> must not exist).
 *   3. Key parity between locales (missing / extra keys).
 *   4. Keys used in code (__(), @lang(), trans(), Translation::label(), data-i18n)
 *      that do not exist in any language file.
 *   5. Dynamic key prefixes built with concatenation.
 *   6. Orphan keys (defined but never referenced).
 *   7. Optional: hard-coded UI strings in admin views/controllers (--literals).
 *
 * Usage:
 *   php artisan platform:translations-audit
 *   php artisan platform:translations-audit --literals
 *   php artisan platform:translations-audit --strict --json
 */
class TranslationsAudit extends Command
{
    protected $signature = 'platform:translations-audit
                            {--literals : Also report hard-coded UI strings in admin views and controllers}
                            {--strict : Return a failure exit code when warnings are present}
                            {--json : Output the report as JSON}';

    protected $description = 'Validate translation files, key parity, code usage and hard-coded strings.';

    private const KEY_PATTERN = '/^(?:[a-z0-9_\-]+::)?[A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)+$/';

    public function handle(TextTranslationLoader $loader): int
    {
        $diagnostics = $loader->diagnostics();
        $locales = $diagnostics['locales'];
        $default = 'en';
        $errors = [];
        $warnings = [];

        // ---- 1. syntax ----
        $textKeys = [];
        foreach ($diagnostics['files'] as $file) {
            $locale = $file['locale'];
            $prefix = $file['namespace'] !== null ? $file['namespace'].'::' : '';

            foreach ($file['items'] as $item) {
                $textKeys[$locale][$prefix.$item] = true;
            }

            foreach ($file['malformed'] as $problem) {
                $errors[] = 'syntax: '.$this->relative($file['path']).':'.$problem['line'].' — '.$problem['text'];
            }

            foreach ($file['duplicates'] as $duplicate) {
                $errors[] = 'duplicate key: '.$this->relative($file['path']).' — '.$prefix.$duplicate;
            }
        }

        // ---- 1b. prefix collisions ----
        // Laravel's Translator::addLines() uses Arr::set(), so a key that is
        // also the prefix of another key turns the string value into an array
        // (and every e() call on it throws). Treat it as a hard error.
        foreach ($textKeys as $locale => $keys) {
            $set = $keys;

            foreach (array_keys($keys) as $key) {
                $parts = explode('.', $key);
                $prefix = '';

                foreach ($parts as $index => $part) {
                    $prefix = $prefix === '' ? $part : $prefix.'.'.$part;

                    if ($prefix !== $key && isset($set[$prefix])) {
                        $errors[] = 'key collision: '.$prefix.' is a value and a prefix of '.$key.' ('.$locale.')';
                    }
                }
            }
        }

        // ---- 2. legacy core.php ----
        foreach ($locales as $locale) {
            if (is_file(lang_path($locale.'/core.php'))) {
                $errors[] = 'legacy file: lang/'.$locale.'/core.php exists but is shadowed by core.txt — merge it into core.txt and delete it';
            }
        }

        // ---- PHP lang files (groups other than core) ----
        $phpKeys = [];
        foreach ($locales as $locale) {
            foreach (glob(lang_path($locale.'/*.php')) ?: [] as $path) {
                $group = basename($path, '.php');

                if ($group === 'core') {
                    continue;
                }

                try {
                    $values = require $path;
                } catch (\Throwable) {
                    $errors[] = 'php lang: cannot load '.$this->relative($path);

                    continue;
                }

                if (! is_array($values)) {
                    continue;
                }

                foreach (array_keys(Arr::dot($values)) as $item) {
                    $phpKeys[$locale][$group.'.'.$item] = true;
                }
            }
        }

        // Laravel ships framework defaults (auth, passwords, validation) in the
        // vendor tree; treat them as valid targets so `auth.*` keys are not
        // reported as missing.
        $frameworkLang = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang');

        if (is_dir($frameworkLang)) {
            foreach ($locales as $locale) {
                foreach (glob($frameworkLang.'/'.$locale.'/*.php') ?: [] as $path) {
                    $group = basename($path, '.php');

                    try {
                        $values = require $path;
                    } catch (\Throwable) {
                        continue;
                    }

                    if (! is_array($values)) {
                        continue;
                    }

                    foreach (array_keys(Arr::dot($values)) as $item) {
                        $phpKeys[$locale][$group.'.'.$item] = true;
                    }
                }
            }
        }

        // ---- 3. parity ----
        $defaultKeys = array_keys($textKeys[$default] ?? []);
        sort($defaultKeys);

        foreach ($locales as $locale) {
            if ($locale === $default) {
                continue;
            }

            $missing = array_values(array_diff($defaultKeys, array_keys($textKeys[$locale] ?? [])));
            $extra = array_values(array_diff(array_keys($textKeys[$locale] ?? []), $defaultKeys));

            if ($missing !== []) {
                $warnings[] = 'parity: '.count($missing).' key(s) found in '.$default.' but missing in '.$locale.' — first: '.implode(', ', array_slice($missing, 0, 8));
            }

            if ($extra !== []) {
                $warnings[] = 'parity: '.count($extra).' key(s) only in '.$locale.' (not in '.$default.') — first: '.implode(', ', array_slice($extra, 0, 8));
            }
        }

        // ---- 4/5. usage ----
        [$usages, $dynamicPrefixes] = $this->scanUsage();
        $missingReported = [];

        foreach ($usages as $usage) {
            $key = $usage['key'];
            $where = $this->relative($usage['file']).':'.$usage['line'];

            [$namespace, $item] = $this->splitKey($key);

            foreach ($locales as $locale) {
                if ($namespace !== null) {
                    $exists = isset($textKeys[$locale][$key]);
                } elseif (str_starts_with($item, 'core.')) {
                    $exists = isset($textKeys[$locale][$item]);
                } else {
                    $exists = isset($phpKeys[$locale][$item]);
                }

                if ($exists) {
                    continue;
                }

                $signature = $locale.'|'.$key;
                if (isset($missingReported[$signature])) {
                    continue;
                }
                $missingReported[$signature] = true;

                if ($locale === $default) {
                    $errors[] = 'missing key: '.$key.' (used at '.$where.')';
                } else {
                    $warnings[] = 'missing translation: '.$key.' in '.$locale.' (used at '.$where.')';
                }
            }
        }

        foreach ($dynamicPrefixes as $prefix => $where) {
            $found = false;

            foreach (array_keys($textKeys[$default] ?? []) as $key) {
                if (str_starts_with($key, $prefix)) {
                    $found = true;

                    break;
                }
            }

            if (! $found) {
                $warnings[] = 'dynamic prefix without keys: "'.$prefix.'" ('.$where.')';
            }
        }

        // ---- 6. orphans ----
        $usedKeys = [];
        foreach ($usages as $usage) {
            $usedKeys[$usage['key']] = true;
        }

        foreach (array_keys($textKeys[$default] ?? []) as $key) {
            if (isset($usedKeys[$key])) {
                continue;
            }

            $dynamic = false;
            foreach (array_keys($dynamicPrefixes) as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $dynamic = true;

                    break;
                }
            }

            if ($dynamic) {
                continue;
            }

            $warnings[] = 'orphan key: '.$key.' is defined but never used in code';
        }

        // ---- 7. literals ----
        $literals = $this->option('literals') ? $this->scanLiterals() : [];

        foreach ($literals as $hit) {
            $warnings[] = 'hard-coded: '.$this->relative($hit['file']).':'.$hit['line'].' — '.$hit['text'];
        }

        // ---- output ----
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'locales' => $locales,
                'modules' => $diagnostics['modules'],
                'keys' => array_map(fn (array $keys): int => count($keys), $textKeys),
                'errors' => $errors,
                'warnings' => $warnings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->newLine();
            $this->line('<info>Translations audit</info>');
            $this->line('  locales: '.implode(', ', $locales));
            $this->line('  modules: '.($diagnostics['modules'] === [] ? '—' : implode(', ', $diagnostics['modules'])));
            $this->line('  core keys: '.implode(', ', array_map(
                fn (string $locale): string => $locale.'='.count($textKeys[$locale] ?? []),
                array_filter($locales, fn (string $locale): bool => isset($textKeys[$locale]))
            )));

            $this->report('Errors', $errors, 'error');
            $this->report('Warnings', $warnings, 'comment');

            if ($this->option('literals')) {
                $this->line('  hard-coded strings: '.count($literals));
            }
        }

        if ($errors !== []) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $warnings !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param list<string> $lines */
    private function report(string $title, array $lines, string $style): void
    {
        if ($lines === []) {
            $this->line('  '.$title.': 0');

            return;
        }

        $this->line('  '.$title.': '.count($lines));

        foreach (array_slice($lines, 0, 30) as $line) {
            $this->line('    - <'.$style.'>'.$line.'</'.$style.'>');
        }

        if (count($lines) > 30) {
            $this->line('    … and '.(count($lines) - 30).' more (use --json for the full list)');
        }
    }

    /** @return array{0:null|string,1:string} */
    private function splitKey(string $key): array
    {
        if (str_contains($key, '::')) {
            [$namespace, $item] = explode('::', $key, 2);

            return [$namespace, $item];
        }

        return [null, $key];
    }

    /**
     * @return array{0:list<array{key:string,file:string,line:int}>,1:array<string,string>}
     */
    private function scanUsage(): array
    {
        $patterns = [
            "/__\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            '/__\(\s*"((?:[^"\\\\]|\\\\.){2,200})"/u',
            "/@lang\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            "/\btrans\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            "/Translation::label\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            "/Lang::get\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            "/trans_choice\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            "/@choice\(\s*'((?:[^'\\\\]|\\\\.){2,200})'/u",
            '/data-i18n="([^"]{2,200})"/u',
            "/data-i18n='([^']{2,200})'/u",
        ];

        $dynamicPatterns = [
            "/__\(\s*'([A-Za-z0-9_\.\-]+\.)'\s*\./u",
            "/@lang\(\s*'([A-Za-z0-9_\.\-]+\.)'\s*\./u",
            "/Translation::label\(\s*'([A-Za-z0-9_\.\-]+\.)'\s*\./u",
        ];

        $usages = [];
        $dynamic = [];

        foreach ($this->scanFiles() as $file) {
            $contents = $this->stripComments((string) file_get_contents($file));

            foreach ($patterns as $pattern) {
                if (! preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[1] as [$key, $offset]) {
                    $key = stripcslashes($key);

                    if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                        continue;
                    }

                    $usages[] = [
                        'key' => $key,
                        'file' => $file,
                        'line' => substr_count(substr($contents, 0, $offset), "\n") + 1,
                    ];
                }
            }

            foreach ($dynamicPatterns as $pattern) {
                if (! preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[1] as [$prefix, $offset]) {
                    $dynamic[$prefix] ??= $this->relative($file).':'.(substr_count(substr($contents, 0, $offset), "\n") + 1);
                }
            }
        }

        return [$usages, $dynamic];
    }

    /** @return list<string> */
    private function scanFiles(): array
    {
        $files = [];
        $roots = [
            base_path('app'),
            base_path('routes'),
            resource_path('views'),
            base_path('modules'),
            base_path('config'),
            base_path('database'),
        ];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());

                if (str_contains($path, '/node_modules/') || str_contains($path, '/vendor/')) {
                    continue;
                }

                if (! preg_match('/\.(php|blade\.php)$/', $path)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<array{file:string,line:int,text:string}> */
    private function scanLiterals(): array
    {
        $hits = [];
        $files = [];

        foreach ([resource_path('views/admin'), resource_path('views/layouts'), base_path('app/Http/Controllers/Admin')] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.php$/', $file->getFilename())) {
                    $files[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }

        foreach (glob(base_path('modules/*/resources/views/*.blade.php')) ?: [] as $file) {
            $files[] = str_replace('\\', '/', $file);
        }

        foreach (glob(base_path('modules/*/resources/views/*/*.blade.php')) ?: [] as $file) {
            $files[] = str_replace('\\', '/', $file);
        }

        $patterns = [
            "/->with\(\s*'[a-z_]+'\s*,\s*'((?:[^'\\\\]|\\\\.){4,200})'/u",
            "/\bconfirm\(\s*'((?:[^'\\\\]|\\\\.){4,200})'/u",
            '/>([^<>{}]{4,160})</u',
            '/\b(placeholder|title|aria-label)="([^"{}<>]{4,160})"/u',
        ];

        $isCopy = function (string $text): bool {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

            if (mb_strlen($text) < 4 || ! preg_match('/[A-Za-z\x{0600}-\x{06FF}]/u', $text)) {
                return false;
            }

            if (preg_match('/^[a-z0-9:\-\[\]\/\. ]+$/u', $text)) {
                return false;
            }

            if (preg_match('/^(bg|text|border|flex|grid|items|justify|space|rounded|shadow|font|w|h|p|m|gap)-/', $text)) {
                return false;
            }

            if (preg_match('/@(foreach|endforeach|if|endif|unless|endunless|php|endphp|else|elseif)/', $text)) {
                return false;
            }

            if (preg_match('/[$]|->|&&|\{\{|\}\}|===|!==/', $text)) {
                return false;
            }

            // identifiers, code fragments and brand acronyms are not UI copy
            if (preg_match('/^[a-z]+[A-Z][A-Za-z]*\s*$/', $text)) {
                return false;
            }

            if (preg_match('/^[A-Z](\.[A-Z])+\.?$/', $text)) {
                return false;
            }

            return true;
        };

        foreach (array_unique($files) as $file) {
            $contents = (string) file_get_contents($file);
            $lines = explode("\n", $contents);

            foreach ($lines as $index => $line) {
                if (str_contains($line, '__(') || str_contains($line, '@lang(') || str_contains($line, 'trans(')) {
                    continue;
                }

                if (preg_match('/^\s*(\/\/|\*|<!--|\{\{--)/', $line)) {
                    continue;
                }

                if (str_contains($line, '->')) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    if (! preg_match_all($pattern, $line, $matches)) {
                        continue;
                    }

                    $values = isset($matches[2]) && $matches[2] !== [] ? $matches[2] : $matches[1];

                    foreach ($values as $value) {
                        $value = stripcslashes($value);

                        if (! $isCopy($value)) {
                            continue;
                        }

                        $hits[] = [
                            'file' => $file,
                            'line' => $index + 1,
                            'text' => Str::limit($value, 90),
                        ];
                    }
                }
            }
        }

        return $hits;
    }

    /**
     * Remove comments while keeping offsets/newlines intact, so keys that only
     * appear in docblocks are not treated as used.
     */
    private function stripComments(string $code): string
    {
        $blank = static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]) ?? '';

        $code = preg_replace_callback('/\{\{--.*?--\}\}/s', $blank, $code) ?? $code;
        $code = preg_replace_callback('/<!--.*?-->/s', $blank, $code) ?? $code;
        $code = preg_replace_callback('#/\*.*?\*/#s', $blank, $code) ?? $code;
        $code = preg_replace_callback('/^[ \t]*\/\/.*$/m', $blank, $code) ?? $code;
        $code = preg_replace_callback('/^[ \t]*#.*$/m', $blank, $code) ?? $code;

        return $code;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
    }
}
