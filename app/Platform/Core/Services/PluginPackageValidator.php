<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use App\Platform\Core\Exceptions\PluginPackageValidationException;
use App\Platform\Core\Repositories\PluginRepository;
use Illuminate\Validation\ValidationException;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class PluginPackageValidator
{
    public function __construct(
        private readonly PluginManifestReader $manifests,
        private readonly PluginOwnershipValidator $ownership,
        private readonly PluginDependencyChecker $dependencies,
        private readonly PluginRepository $plugins,
    ) {}

    /**
     * Validate a complete package before files, database state, or assets are changed.
     *
     * @throws PluginPackageValidationException
     */
    public function validate(string $pluginPath): PluginManifest
    {
        $errors = [];
        $root = realpath($pluginPath);

        if ($root === false || ! is_dir($root) || ! is_readable($root)) {
            throw new PluginPackageValidationException(['The extracted plugin root is missing or unreadable.']);
        }

        try {
            $manifest = $this->manifests->readFromPluginPath($root);
        } catch (ValidationException $exception) {
            throw new PluginPackageValidationException(array_values($exception->validator->errors()->all()));
        } catch (Throwable $exception) {
            throw new PluginPackageValidationException([$exception->getMessage()]);
        }

        $this->validatePackageTree($root, $errors);
        $this->validateProvider($root, $manifest, $errors);
        $this->validateRoutes($root, $manifest, $errors);
        $this->validateMigrations($root, $manifest, $errors);
        $this->validateAssets($root, $manifest, $errors);
        $this->validateLifecycle($root, $manifest, $errors);
        $this->validateDocumentation($root, $manifest, $errors);

        try {
            $this->ownership->validate($root, $manifest);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        $missingDependencies = $this->dependencies->missingDependencies($manifest, $this->plugins->all());

        if ($missingDependencies !== []) {
            $errors[] = 'Required plugin dependencies are not installed: '.implode(', ', $missingDependencies).'.';
        }

        if ($errors !== []) {
            throw new PluginPackageValidationException(array_values(array_unique($errors)));
        }

        return $manifest;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validatePackageTree(string $root, array &$errors): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            $relative = $this->relativePath($root, $entry->getPathname());

            if ($entry->isLink()) {
                $errors[] = "Symbolic links are not allowed in plugin packages [{$relative}].";

                continue;
            }

            if ($this->containsBlockedSegment($relative)) {
                $errors[] = "Plugin package contains a blocked directory [{$relative}].";

                continue;
            }

            if ($entry->isFile() && strtolower($entry->getExtension()) === 'php') {
                $this->validatePhpSyntax($entry->getPathname(), $relative, $errors);
            }
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateProvider(string $root, PluginManifest $manifest, array &$errors): void
    {
        $provider = ltrim(str_replace('\\\\', '\\', trim($manifest->provider)), '\\');
        $providerFile = data_get($manifest->manifest, 'provider_file');
        $path = null;

        if (is_string($providerFile) && trim($providerFile) !== '') {
            $path = $this->existingFile($root, $providerFile);

            if ($path === null) {
                $errors[] = "Declared provider file [{$providerFile}] is missing or outside the package.";

                return;
            }
        } else {
            $class = class_basename($provider);

            foreach (["src/{$class}.php", "{$class}.php", 'ServiceProvider.php'] as $candidate) {
                $path = $this->existingFile($root, $candidate);

                if ($path !== null) {
                    break;
                }
            }

            if ($path === null) {
                $errors[] = "Provider class [{$provider}] has no provider_file or discoverable PHP file.";

                return;
            }
        }

        $source = (string) file_get_contents($path);
        $separator = strrpos($provider, '\\');
        $namespace = $separator === false ? '' : substr($provider, 0, $separator);
        $class = $separator === false ? $provider : substr($provider, $separator + 1);
        $namespacePattern = $namespace === ''
            ? ''
            : 'namespace\s+'.preg_quote($namespace, '/').'\s*;';

        if (
            ($namespacePattern !== '' && preg_match('/'.$namespacePattern.'/i', $source) !== 1)
            || preg_match('/\bclass\s+'.preg_quote($class, '/').'\b/i', $source) !== 1
            || preg_match('/\bextends\s+(?:\\\\?Illuminate\\\\Support\\\\)?ServiceProvider\b/i', $source) !== 1
        ) {
            $errors[] = "Provider file [{$this->relativePath($root, $path)}] does not declare "
                ."[{$provider}] as a Laravel ServiceProvider.";
        }

        if (
            preg_match('/\bloadRoutesFrom\s*\(/', $source) === 1
            || preg_match('/\bRoute\s*::/', $source) === 1
        ) {
            $errors[] = 'Plugin providers cannot register routes directly; declare route files in module.json.';
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateRoutes(string $root, PluginManifest $manifest, array &$errors): void
    {
        $declared = [];

        foreach (['web', 'admin', 'api'] as $scope) {
            $definition = data_get($manifest->manifest, "routes.{$scope}");

            if ($definition === null) {
                continue;
            }

            if (! is_array($definition)) {
                $errors[] = "Route catalog [routes.{$scope}] must be an object.";

                continue;
            }

            $file = data_get($definition, 'file');

            if (! is_string($file) || trim($file) === '') {
                $errors[] = "Route catalog [routes.{$scope}.file] is required.";

                continue;
            }

            $declared[] = str_replace('\\', '/', trim($file));

            if ($this->existingFile($root, $file) === null) {
                $errors[] = "Declared {$scope} route file [{$file}] is missing or outside the package.";
            }
        }

        foreach (glob($root.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'*.php') ?: [] as $routeFile) {
            $relative = $this->relativePath($root, $routeFile);

            if (! in_array($relative, $declared, true)) {
                $errors[] = "Route file [{$relative}] exists but is not declared in the route catalog.";
            }
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateMigrations(string $root, PluginManifest $manifest, array &$errors): void
    {
        $configured = data_get($manifest->manifest, 'install.migrations')
            ?: data_get($manifest->manifest, 'migrations')
            ?: 'database/migrations';
        $tables = (array) data_get($manifest->manifest, 'uninstall.tables', []);
        $path = $this->existingDirectory($root, (string) $configured);
        $migrationFiles = $path === null ? [] : (glob($path.DIRECTORY_SEPARATOR.'*.php') ?: []);

        if ($tables !== [] && $migrationFiles === []) {
            $errors[] = 'The plugin owns database tables but has no readable migration files.';
        } elseif (data_get($manifest->manifest, 'migrations') !== null && $migrationFiles === []) {
            $errors[] = "Declared migration directory [{$configured}] is missing or empty.";
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateAssets(string $root, PluginManifest $manifest, array &$errors): void
    {
        $assets = data_get($manifest->manifest, 'assets');
        $defaultSource = $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'assets';
        $hasSourceAssets = is_dir($defaultSource) && $this->assetFiles($defaultSource) !== [];

        if ($assets === null) {
            if ($hasSourceAssets) {
                $errors[] = 'Asset files exist under resources/assets but module.json has no assets catalog.';
            }

            return;
        }

        if (! is_array($assets)) {
            $errors[] = 'The assets catalog must be an object.';

            return;
        }

        $source = data_get($assets, 'source', data_get($assets, 'public', 'resources/assets'));
        $sourcePath = is_string($source) ? $this->existingDirectory($root, $source) : null;

        if ($sourcePath === null) {
            $errors[] = 'The declared asset source directory is missing or outside the package.';

            return;
        }

        $catalog = [];

        foreach (['admin', 'frontend', 'guest'] as $scope) {
            foreach (['styles' => 'css', 'scripts' => 'js'] as $kind => $extension) {
                $paths = data_get($assets, "{$scope}.{$kind}", []);

                if (is_string($paths)) {
                    $paths = [$paths];
                }

                if (! is_array($paths)) {
                    $errors[] = "Asset catalog [assets.{$scope}.{$kind}] must be an array.";

                    continue;
                }

                foreach ($paths as $path) {
                    if (! is_string($path) || ! $this->safeRelativePath($path, $extension)) {
                        $errors[] = "Asset catalog [assets.{$scope}.{$kind}] contains an unsafe {$extension} path.";

                        continue;
                    }

                    $catalog[] = str_replace('\\', '/', trim($path));

                    if ($this->existingFile($sourcePath, $path) === null) {
                        $errors[] = "Declared {$scope} {$kind} asset [{$path}] is missing.";
                    }
                }
            }
        }

        $legacyAdminStyles = data_get($assets, 'admin_styles', []);
        if (is_string($legacyAdminStyles)) {
            $legacyAdminStyles = [$legacyAdminStyles];
        }
        if (is_array($legacyAdminStyles)) {
            $catalog = array_merge($catalog, array_values(array_filter($legacyAdminStyles, 'is_string')));
        }

        $sourceFiles = $this->assetFiles($sourcePath);

        if ($sourceFiles !== [] && $catalog === []) {
            $errors[] = 'The asset source contains CSS/JS files but no styles or scripts are declared in the catalog.';

            return;
        }

        $uncatalogued = array_values(array_diff($sourceFiles, array_unique($catalog)));

        if ($uncatalogued !== []) {
            $errors[] = 'CSS/JS files are missing from the assets catalog: '.implode(', ', $uncatalogued).'.';
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateLifecycle(string $root, PluginManifest $manifest, array &$errors): void
    {
        $file = data_get($manifest->manifest, 'lifecycle.file');

        if (is_string($file) && trim($file) !== '' && $this->existingFile($root, $file) === null) {
            $errors[] = "Declared lifecycle file [{$file}] is missing or outside the package.";
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateDocumentation(string $root, PluginManifest $manifest, array &$errors): void
    {
        $docs = data_get($manifest->manifest, 'docs');

        if (is_string($docs) && trim($docs) !== '' && $this->existingFile($root, $docs) === null) {
            $errors[] = "Declared plugin documentation [{$docs}] is missing.";
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validatePhpSyntax(string $path, string $relative, array &$errors): void
    {
        try {
            token_get_all((string) file_get_contents($path), TOKEN_PARSE);
        } catch (ParseError $exception) {
            $errors[] = "PHP file [{$relative}] has invalid syntax: {$exception->getMessage()}";
        }
    }

    private function existingFile(string $root, string $relative): ?string
    {
        $path = $this->containedPath($root, $relative);

        return $path !== null && is_file($path) && is_readable($path) ? $path : null;
    }

    private function existingDirectory(string $root, string $relative): ?string
    {
        $path = $this->containedPath($root, $relative);

        return $path !== null && is_dir($path) && is_readable($path) ? $path : null;
    }

    private function containedPath(string $root, string $relative): ?string
    {
        if (! $this->safeRelativePath($relative)) {
            return null;
        }

        $root = realpath($root);
        $candidate = $root === false
            ? false
            : realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relative)));

        if (
            $root === false
            || $candidate === false
            || ! str_starts_with(
                rtrim($candidate, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
                rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
            )
        ) {
            return null;
        }

        return $candidate;
    }

    private function safeRelativePath(string $path, ?string $extension = null): bool
    {
        $path = str_replace('\\', '/', trim($path));

        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && preg_match('/^[A-Za-z0-9_.\/-]+$/', $path) === 1
            && ($extension === null || str_ends_with(strtolower($path), '.'.$extension));
    }

    /**
     * @return array<int, string>
     */
    private function assetFiles(string $source): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && in_array(strtolower($entry->getExtension()), ['css', 'js'], true)) {
                $files[] = $this->relativePath($source, $entry->getPathname());
            }
        }

        return $files;
    }

    private function containsBlockedSegment(string $path): bool
    {
        $segments = explode('/', strtolower(str_replace('\\', '/', $path)));

        return array_intersect($segments, ['.git', 'vendor', 'node_modules']) !== [];
    }

    private function relativePath(string $root, string $path): string
    {
        return str_replace('\\', '/', ltrim(substr($path, strlen($root)), '/\\'));
    }
}
