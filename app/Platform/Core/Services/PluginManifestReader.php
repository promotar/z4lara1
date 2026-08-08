<?php

namespace App\Platform\Core\Services;

use App\Platform\Core\DTOs\PluginManifest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;

class PluginManifestReader
{
    public function __construct(private readonly PlatformVersion $platformVersion) {}

    /**
     * @throws ValidationException
     */
    public function read(string $manifestPath): PluginManifest
    {
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException("Plugin manifest not found at [{$manifestPath}].");
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw new InvalidArgumentException("Plugin manifest could not be read at [{$manifestPath}].");
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "Plugin manifest contains invalid JSON at [{$manifestPath}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException("Plugin manifest must decode to an object at [{$manifestPath}].");
        }

        $data = $this->validate($data);
        $constraint = $data['platform_version'] ?? null;

        if (is_string($constraint) && ! $this->platformVersion->supports($constraint)) {
            throw new InvalidArgumentException(
                "Plugin requires platform version [{$constraint}], current version is [{$this->platformVersion->current()}].",
            );
        }

        return PluginManifest::fromArray($data, $manifestPath);
    }

    /**
     * @throws ValidationException
     */
    public function readFromPluginPath(string $pluginPath): PluginManifest
    {
        return $this->read(rtrim($pluginPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'module.json');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'version' => ['required', 'string', 'max:50', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/'],
            'provider' => ['required', 'string', 'max:255', 'regex:/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+[A-Za-z_][A-Za-z0-9_]*$/'],
            'provider_file' => ['nullable', 'string', 'regex:/^(?!.*\.\.)(?![\/\\\\])[A-Za-z0-9_.\/-]+\.php$/'],
            'description' => ['required', 'string', 'max:1000'],
            'author' => ['required', 'string', 'max:120'],
            'dependencies' => ['nullable', 'array'],
            'dependencies.*' => ['nullable'],
            'platform_version' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:feature,theme,service'],
            'migrations' => ['nullable', 'string', 'regex:/^(?!.*\.\.)(?![\/\\\\])[A-Za-z0-9_.\/-]+$/'],
            'install.migrations' => ['nullable', 'string', 'regex:/^(?!.*\.\.)(?![\/\\\\])[A-Za-z0-9_.\/-]+$/'],
            'uninstall' => ['required', 'array'],
            'uninstall.script' => ['prohibited'],
            'uninstall.tables' => ['present', 'array'],
            'uninstall.tables.*' => ['string', 'distinct', 'regex:/^[A-Za-z0-9_]+$/'],
            'uninstall.settings' => ['present', 'array'],
            'uninstall.settings.*' => ['string', 'distinct', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'uninstall.storage_paths' => ['present', 'array'],
            'uninstall.storage_paths.*' => ['array:disk,path'],
            'uninstall.storage_paths.*.disk' => ['required', 'string', 'in:local,public'],
            'uninstall.storage_paths.*.path' => ['required', 'string', 'regex:/^(?!.*\.\.)(?![\/\\\\])[A-Za-z0-9_.\/-]+$/'],
            'uninstall.records' => ['present', 'array'],
            'uninstall.records.*' => ['array:table,column,values'],
            'uninstall.records.*.table' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'uninstall.records.*.column' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'uninstall.records.*.values' => ['required', 'array', 'min:1'],
            'uninstall.columns' => ['present', 'array'],
            'uninstall.columns.*' => ['array:table,columns'],
            'uninstall.columns.*.table' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'uninstall.columns.*.columns' => ['required', 'array', 'min:1'],
            'uninstall.columns.*.columns.*' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'uninstall.operation_target_prefixes' => ['present', 'array'],
            'uninstall.operation_target_prefixes.*' => ['string', 'distinct', 'regex:/^[A-Za-z0-9_.:-]+$/'],
        ])->validate();

        return $data;
    }
}
