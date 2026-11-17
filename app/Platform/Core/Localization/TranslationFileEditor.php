<?php

namespace App\Platform\Core\Localization;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\VarExporter\VarExporter;
use Throwable;

/**
 * Reads and writes PHP translation files without touching any module that is
 * not the owner of the key.
 *
 * Key resolution:
 *   core.auth.sign_in        -> lang/{locale}/core.php        [auth][sign_in]
 *   auth.failed              -> lang/{locale}/auth.php        [failed]
 *   lms::courses.title       -> modules/lms/lang/{locale}/courses.php [title]
 */
final class TranslationFileEditor
{
    public const DEFAULT = 'en';

    /**
     * @return array{key:string,namespace:?string,group:string,item:string,locale:string,path:string}|null
     */
    public function resolve(string $key, string $locale): ?array
    {
        if (! $this->isAvailableLocale($locale) || strlen($key) > 255) {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_\-]+(?:::[a-zA-Z0-9_\-]+)?(?:\.[a-zA-Z0-9_\-]+)+$/', $key) !== 1) {
            return null;
        }

        $namespace = null;

        if (str_contains($key, '::')) {
            [$namespace, $rest] = explode('::', $key, 2);
            $segments = explode('.', $rest);
            $group = (string) array_shift($segments);
            $item = implode('.', $segments);

            $moduleRoot = base_path('modules/'.$namespace);

            if (! is_dir($moduleRoot)) {
                return null;
            }

            $base = is_dir($moduleRoot.'/lang') || ! is_dir($moduleRoot.'/resources/lang')
                ? $moduleRoot.'/lang'
                : $moduleRoot.'/resources/lang';

            $path = $base.'/'.$locale.'/'.$group.'.php';
        } else {
            $segments = explode('.', $key);
            $group = (string) array_shift($segments);
            $item = implode('.', $segments);
            $path = lang_path($locale.'/'.$group.'.php');
        }

        if ($group === '' || $item === '') {
            return null;
        }

        return [
            'key' => $key,
            'namespace' => $namespace,
            'group' => $group,
            'item' => $item,
            'locale' => $locale,
            'path' => $path,
        ];
    }

    public function value(string $key, string $locale): ?string
    {
        $target = $this->resolve($key, $locale);

        if ($target === null) {
            return null;
        }

        $value = Arr::get($this->read($target['path']), $target['item']);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return array{key:string,locale:string,value:string,path:string}|null
     */
    public function save(string $key, string $locale, string $value): ?array
    {
        $target = $this->resolve($key, $locale);

        if ($target === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 5000) {
            return null;
        }

        if (File::exists($target['path']) && ! is_writable($target['path'])) {
            Log::warning('platform.translation.not_writable', ['path' => $target['path']]);

            return null;
        }

        $directory = dirname($target['path']);

        if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0775, true)) {
            return null;
        }

        try {
            $data = $this->read($target['path']);
            Arr::set($data, $target['item'], $value);

            $this->backup($target['path']);
            $this->write($target['path'], $data);

            Log::info('platform.translation.saved', [
                'key' => $key,
                'locale' => $locale,
                'path' => $target['path'],
                'user_id' => auth()->id(),
            ]);

            return [
                'key' => $key,
                'locale' => $locale,
                'value' => $value,
                'path' => $target['path'],
            ];
        } catch (Throwable $exception) {
            Log::error('platform.translation.failed', [
                'key' => $key,
                'locale' => $locale,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function isValidGroup(string $group): bool
    {
        return in_array($group, ['json', 'text'], true) || preg_match('/^[a-z0-9_\-]+$/', $group) === 1;
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

        if (! in_array(self::DEFAULT, $locales, true)) {
            array_unshift($locales, self::DEFAULT);
        }

        $locales = array_values(array_unique($locales));
        usort($locales, static function (string $a, string $b): int {
            if ($a === self::DEFAULT) {
                return -1;
            }

            if ($b === self::DEFAULT) {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $locales;
    }

    public function isAvailableLocale(string $locale): bool
    {
        return in_array($locale, $this->locales(), true);
    }

    /** @return array<string, string> */
    public function localeLabels(): array
    {
        $labels = [];
        $path = lang_path('languages.txt');

        if (is_file($path)) {
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
                $line = trim($line);

                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }

                [$code, $label] = explode('=', $line, 2);
                $code = trim($code);
                $label = trim($label);

                if (strlen($label) >= 2 && str_starts_with($label, '"') && str_ends_with($label, '"')) {
                    $label = stripcslashes(substr($label, 1, -1));
                }

                if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code) === 1 && $label !== '') {
                    $labels[$code] = $label;
                }
            }
        }

        $result = [];

        foreach ($this->locales() as $code) {
            $result[$code] = $labels[$code] ?? strtoupper($code);
        }

        return $result;
    }

    public function createLocale(string $code, string $label, string $source, ?string $content = null, ?string $flag = null, ?string $module = null): bool
    {
        $code = trim($code);

        if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $code) !== 1 || ! $this->isAvailableLocale($source)) {
            return false;
        }

        if ($module !== null) {
            if (preg_match('/^[a-z0-9_\-]+$/', $module) !== 1 || ! is_dir(base_path('modules/'.$module))) {
                return false;
            }

            $path = base_path('modules/'.$module.'/lang/'.$code.'/core.txt');
        } else {
            $path = lang_path($code.'/core.txt');
        }

        if (is_file($path)) {
            return false;
        }

        $directory = dirname($path);

        if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0775, true)) {
            return false;
        }

        try {
            if ($content === null) {
                $sourcePath = $module !== null
                    ? base_path('modules/'.$module.'/lang/'.$source.'/core.txt')
                    : lang_path($source.'/core.txt');

                $content = is_file($sourcePath) ? (string) file_get_contents($sourcePath) : '';
            }

            $this->replaceFile($path, $content);
            $this->saveLanguageLabel($code, $label);

            if ($flag !== null && trim($flag) !== '') {
                $this->saveLanguageFlag($code, $flag);
            }

            if ($module !== null && ! is_file(lang_path($code.'/core.txt'))) {
                $sourceCore = lang_path($source.'/core.txt');

                if (is_file($sourceCore)) {
                    $coreDirectory = lang_path($code);

                    if (! File::isDirectory($coreDirectory)) {
                        File::makeDirectory($coreDirectory, 0775, true);
                    }

                    $this->replaceFile($coreDirectory.'/core.txt', (string) file_get_contents($sourceCore));
                }
            }

            return true;
        } catch (Throwable $exception) {
            Log::error('platform.translation.locale_create_failed', [
                'locale' => $code,
                'module' => $module,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function saveLanguageLabel(string $code, string $label): void
    {
        $path = lang_path('languages.txt');
        $labels = [];

        if (is_file($path)) {
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
                $line = trim($line);

                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }

                [$existingCode, $existingLabel] = explode('=', $line, 2);
                $existingCode = trim($existingCode);
                $existingLabel = trim($existingLabel);

                if (strlen($existingLabel) >= 2 && str_starts_with($existingLabel, '"') && str_ends_with($existingLabel, '"')) {
                    $existingLabel = stripcslashes(substr($existingLabel, 1, -1));
                }

                if ($existingCode !== '') {
                    $labels[$existingCode] = $existingLabel;
                }
            }
        }

        $labels[$code] = trim($label) !== '' ? trim($label) : strtoupper($code);
        ksort($labels);

        $content = '';

        foreach ($labels as $existingCode => $existingLabel) {
            $content .= $existingCode.' = "'.addcslashes($existingLabel, "\\\"")."\"\n";
        }

        $this->replaceFile($path, $content);
    }

    /** @return array<string, string> */
    public function localeFlags(): array
    {
        $stored = [];
        $path = lang_path('flags.txt');

        if (is_file($path)) {
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
                $line = trim($line);

                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }

                [$code, $flag] = explode('=', $line, 2);
                $code = trim($code);
                $flag = trim($flag);

                if (strlen($flag) >= 2 && str_starts_with($flag, '"') && str_ends_with($flag, '"')) {
                    $flag = stripcslashes(substr($flag, 1, -1));
                }

                if ($code !== '' && $flag !== '') {
                    $stored[$code] = $flag;
                }
            }
        }

        $defaults = [
            'en' => 'ðŸ‡¬ðŸ‡§', 'ar' => 'ðŸ‡¸ðŸ‡¦', 'es' => 'ðŸ‡ªðŸ‡¸', 'fr' => 'ðŸ‡«ðŸ‡·', 'de' => 'ðŸ‡©ðŸ‡ª',
            'tr' => 'ðŸ‡¹ðŸ‡·', 'it' => 'ðŸ‡®ðŸ‡¹', 'pt' => 'ðŸ‡µðŸ‡¹', 'ru' => 'ðŸ‡·ðŸ‡º', 'zh' => 'ðŸ‡¨ðŸ‡³',
            'hi' => 'ðŸ‡®ðŸ‡³', 'fa' => 'ðŸ‡®ðŸ‡·', 'he' => 'ðŸ‡®ðŸ‡±', 'ur' => 'ðŸ‡µðŸ‡°', 'nl' => 'ðŸ‡³ðŸ‡±',
            'pl' => 'ðŸ‡µðŸ‡±', 'ja' => 'ðŸ‡¯ðŸ‡µ', 'ko' => 'ðŸ‡°ðŸ‡·', 'id' => 'ðŸ‡®ðŸ‡©', 'ms' => 'ðŸ‡²ðŸ‡¾',
        ];

        $flags = [];

        foreach ($this->locales() as $code) {
            $base = strtolower(strtok($code, '-') ?: $code);
            $flags[$code] = $stored[$code] ?? $defaults[$code] ?? $defaults[$base] ?? 'ðŸŒ';
        }

        return $flags;
    }

    public function saveLanguageFlag(string $code, string $flag): void
    {
        $path = lang_path('flags.txt');
        $flags = [];

        if (is_file($path)) {
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
                $line = trim($line);

                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }

                [$existingCode, $existingFlag] = explode('=', $line, 2);
                $existingCode = trim($existingCode);
                $existingFlag = trim($existingFlag);

                if (strlen($existingFlag) >= 2 && str_starts_with($existingFlag, '"') && str_ends_with($existingFlag, '"')) {
                    $existingFlag = stripcslashes(substr($existingFlag, 1, -1));
                }

                if ($existingCode !== '') {
                    $flags[$existingCode] = $existingFlag;
                }
            }
        }

        $flags[$code] = trim($flag);
        ksort($flags);

        $content = '';

        foreach ($flags as $existingCode => $existingFlag) {
            $content .= $existingCode.' = "'.addcslashes($existingFlag, "\\\"")."\"\n";
        }

        $this->replaceFile($path, $content);
    }

    public function pathFor(string $locale, string $group, ?string $module = null): ?string
    {
        if (! $this->isAvailableLocale($locale) || ! $this->isValidGroup($group)) {
            return null;
        }

        if ($module !== null) {
            if (preg_match('/^[a-z0-9_\-]+$/', $module) !== 1) {
                return null;
            }

            $root = base_path('modules/'.$module);

            if (! is_dir($root)) {
                return null;
            }

            return $group === 'text'
                ? $root.'/lang/'.$locale.'/core.txt'
                : $root.'/lang/'.$locale.'/'.$group.'.php';
        }

        if ($group === 'json') { return lang_path($locale.'.json'); }

        if ($group === 'text') { return $module !== null ? base_path('modules/'.$module.'/lang/'.$locale.'/core.txt') : lang_path($locale.'/core.txt'); }

        return lang_path($locale.'/'.$group.'.php');
    }

    public function groupExists(string $locale, string $group, ?string $module = null): bool
    {
        $path = $this->pathFor($locale, $group, $module);

        return $path !== null && is_file($path);
    }

    /** @return array<string, mixed> */
    public function readGroup(string $locale, string $group, ?string $module = null): array
    {
        if ($group === 'json' && $module === null) {
            return $this->readJson($locale);
        }

        $path = $this->pathFor($locale, $group, $module);

        if ($path === null) {
            return [];
        }

        if ($group === 'text') {
            return $this->readText($path);
        }

        return $this->read($path);
    }

    /**
     * @return list<array{locale:string,group:string,module:?string,path:string,keys:int,filled:int,missing:int,updated:int}>
     */
    public function listFiles(): array
    {
        $rows = $this->coreFiles();

        foreach ($this->activeModules() as $module) {
            $this->ensureModuleFiles($module);

            foreach ($this->moduleFiles($module) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{locale:string,group:string,module:?string,path:string,keys:int,filled:int,missing:int,updated:int}>
     */
    private function coreFiles(): array
    {
        $rows = [];

        foreach ($this->locales() as $locale) {
            $other = $locale === 'en' ? 'ar' : 'en';
            $groups = [];

            foreach (glob(lang_path($locale.'/*.php')) ?: [] as $file) {
                $groups[] = basename($file, '.php');
            }

            if (is_file(lang_path($locale.'.json'))) {
                $groups[] = 'json';
            }

            if (is_file(lang_path($locale.'/core.txt'))) {
                $groups[] = 'text';
            }

            sort($groups);

            foreach (array_unique($groups) as $group) {
                $path = $this->pathFor($locale, $group);
                $flat = $this->flatten($this->readGroup($locale, $group));
                $otherFlat = $this->flatten($this->readGroup($other, $group));

                $rows[] = [
                    'locale' => $locale,
                    'group' => $group,
                    'module' => null,
                    'path' => $path ?? '',
                    'keys' => count($flat),
                    'filled' => count(array_filter($flat, static fn (mixed $value): bool => trim((string) $value) !== '')),
                    'missing' => count(array_diff_key($otherFlat, $flat)),
                    'updated' => is_file((string) $path) ? (int) filemtime((string) $path) : 0,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{locale:string,group:string,module:?string,path:string,keys:int,filled:int,missing:int,updated:int}>
     */
    private function moduleFiles(string $module): array
    {
        $rows = [];

        foreach ($this->locales() as $locale) {
            $other = $locale === 'en' ? 'ar' : 'en';

            foreach (glob(base_path('modules/'.$module.'/lang/'.$locale.'/*.txt')) ?: [] as $file) {
                $group = 'text';
                $path = $this->pathFor($locale, $group, $module);
                $flat = $this->flatten($this->readGroup($locale, $group, $module));
                $otherFlat = $this->flatten($this->readGroup($other, $group, $module));

                $rows[] = [
                    'locale' => $locale,
                    'group' => $group,
                    'module' => $module,
                    'path' => $path ?? '',
                    'keys' => count($flat),
                    'filled' => count(array_filter($flat, static fn (mixed $value): bool => trim((string) $value) !== '')),
                    'missing' => count(array_diff_key($otherFlat, $flat)),
                    'updated' => is_file((string) $path) ? (int) filemtime((string) $path) : 0,
                ];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    public function activeModules(): array
    {
        return array_keys($this->moduleLabels());
    }

    /** @return array<string, string> */
    public function moduleLabels(): array
    {
        try {
            if (Schema::hasTable('plugins')) {
                $labels = [];

                foreach (DB::table('plugins')->whereIn('status', ['active', 'ACTIVE'])->orderBy('name')->get(['slug', 'name']) as $plugin) {
                    if (is_string($plugin->slug) && is_dir(base_path('modules/'.$plugin->slug))) {
                        $labels[$plugin->slug] = (string) $plugin->name;
                    }
                }

                if ($labels !== []) {
                    return $labels;
                }
            }
        } catch (Throwable) {
            // Fall back to the filesystem when the database is unavailable.
        }

        $labels = [];

        foreach (glob(base_path('modules/*/lang'), GLOB_ONLYDIR) ?: [] as $path) {
            $slug = basename(dirname($path));
            $labels[$slug] = $slug;
        }

        return $labels;
    }

    /**
     * Creates the plugin-owned language files when they do not exist yet.
     * Called when a plugin is activated (and lazily when listed).
     */
    public function ensureModuleFiles(string $module): bool
    {
        if (preg_match('/^[a-z0-9_\-]+$/', $module) !== 1) {
            return false;
        }

        $root = base_path('modules/'.$module);

        if (! is_dir($root)) {
            return false;
        }

        $created = false;

        foreach ($this->locales() as $locale) {
            $directory = $root.'/lang/'.$locale;
            $file = $directory.'/core.txt';

            if (! is_dir($directory)) {
                File::makeDirectory($directory, 0775, true);
            }

            if (! is_file($file)) {
                $this->replaceFile($file, "");
                $created = true;
            }
        }

        return $created;
    }

    /**
     * @param array<string, string> $values
     */
    public function saveGroup(string $locale, string $group, array $values, ?string $module = null): int
    {
        $path = $this->pathFor($locale, $group, $module);

        if ($path === null) {
            return 0;
        }

        if (is_file($path) && ! is_writable($path)) {
            Log::warning('platform.translation.not_writable', ['path' => $path]);

            return 0;
        }

        $directory = dirname($path);

        if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0775, true)) {
            return 0;
        }

        if ($group === 'text') {
            return $this->saveText($path, $locale, $values);
        }

        $data = $group === 'json' ? $this->readJson($locale) : $this->read($path);
        $saved = 0;

        foreach ($values as $key => $value) {
            $key = trim((string) $key);
            $value = trim((string) $value);

            if ($key === '' || $value === '' || mb_strlen($value) > 5000) {
                continue;
            }

            if ($group === 'json') {
                $data[$key] = $value;
                $saved++;

                continue;
            }

            if (preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/', $key) !== 1) {
                continue;
            }

            Arr::set($data, $key, $value);
            $saved++;
        }

        if ($saved === 0) {
            return 0;
        }

        try {
            $this->backup($path);

            if ($group === 'json') {
                $this->writeJson($path, $data);
            } else {
                $this->write($path, $data);
            }

            Log::info('platform.translation.group_saved', [
                'locale' => $locale,
                'group' => $group,
                'count' => $saved,
                'path' => $path,
                'user_id' => auth()->id(),
            ]);

            return $saved;
        } catch (Throwable $exception) {
            Log::error('platform.translation.group_failed', [
                'locale' => $locale,
                'group' => $group,
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /** @return array<string, mixed> */
    public function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $full);
            } else {
                $flat[$full] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $flat;
    }

    public function rawContent(string $locale, string $group, ?string $module = null): ?string
    {
        $path = $this->pathFor($locale, $group, $module);

        return $path !== null && is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function saveRawContent(string $locale, string $group, ?string $module, string $content): bool
    {
        $path = $this->pathFor($locale, $group, $module);

        if ($path === null) {
            return false;
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! str_contains($line, '=')) {
                return false;
            }

            [$key, $value] = explode('=', $line, 2);

            if (trim($key) === '' || trim($value) === '') {
                return false;
            }

            $lines[] = trim($key).' = '.$this->quoteTextValue($this->parseTextValue($value));
        }

        if ($lines === []) {
            return false;
        }

        try {
            $this->backup($path);
            $this->replaceFile($path, implode("\n", $lines)."\n");

            Log::info('platform.translation.raw_saved', [
                'locale' => $locale,
                'group' => $group,
                'module' => $module,
                'path' => $path,
                'user_id' => auth()->id(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('platform.translation.raw_failed', [
                'locale' => $locale,
                'group' => $group,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array<string, string> */
    private function readText(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path))) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = $this->parseTextValue(substr($line, $separator + 1));

            if ($key === '' || $value === '') {
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /** @param array<string, string> $values */
    private function saveText(string $path, string $locale, array $values): int
    {
        $data = $this->readText($path);
        $saved = 0;

        foreach ($values as $key => $value) {
            $key = trim((string) $key);
            $value = trim(str_replace(["\r", "\n"], ' ', (string) $value));

            if ($key === '' || $value === '' || str_contains($key, ':') || mb_strlen($value) > 5000) {
                continue;
            }

            $data[$key] = $value;
            $saved++;
        }

        if ($saved === 0) {
            return 0;
        }

        try {
            $this->backup($path);
            $this->writeText($path, $data);

            Log::info('platform.translation.group_saved', [
                'locale' => $locale,
                'group' => 'text',
                'count' => $saved,
                'path' => $path,
                'user_id' => auth()->id(),
            ]);

            return $saved;
        } catch (Throwable $exception) {
            Log::error('platform.translation.group_failed', [
                'locale' => $locale,
                'group' => 'text',
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /** @param array<string, string> $data */
    private function writeText(string $path, array $data): void
    {
        $content = '';

        foreach ($data as $key => $value) {
            $content .= $key.' = '.$this->quoteTextValue((string) $value)."\n";
        }

        $this->replaceFile($path, $content);
    }

    private function parseTextValue(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private function quoteTextValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return '"'.addcslashes($value, "\\\"").'"';
    }

    /** @return array<string, mixed> */
    private function readJson(string $locale): array
    {
        $path = lang_path($locale.'.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            return [];
        }

        try {
            $data = include $path;
        } catch (Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function write(string $path, array $data): void
    {
        $export = class_exists(VarExporter::class)
            ? VarExporter::export($data)
            : var_export($data, true);

        $content = "<?php\n\nreturn ".$export.";\n";
        $this->replaceFile($path, $content);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            throw new \RuntimeException('Unable to encode the JSON translation file.');
        }

        $this->replaceFile($path, $content."\n");
    }

    private function replaceFile(string $path, string $content): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write the temporary translation file.');
        }

        @chmod($temporary, 0664);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException('Unable to replace the translation file.');
        }

        clearstatcache(true, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function backup(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        $destination = storage_path('app/platform/translations/backups/'.date('Ymd-His').'/'.$relative);

        File::ensureDirectoryExists(dirname($destination));
        File::copy($path, $destination);
    }
}




