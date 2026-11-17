<?php

namespace App\Platform\Core\DTOs;

class PluginManifest
{
    /**
     * @param array<string, mixed> $manifest
     * @param array<int|string, mixed> $dependencies
     */
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $version,
        public readonly string $provider,
        public readonly ?string $description = null,
        public readonly ?string $author = null,
        public readonly array $dependencies = [],
        public readonly array $manifest = [],
        public readonly ?string $sourcePath = null,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?string $sourcePath = null): self
    {
        return new self(
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            version: (string) $data['version'],
            provider: (string) $data['provider'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            author: isset($data['author']) ? (string) $data['author'] : null,
            dependencies: $data['dependencies'] ?? [],
            manifest: $data,
            sourcePath: $sourcePath,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'provider' => $this->provider,
            'dependencies' => $this->dependencies,
            'manifest' => $this->manifest,
            'source_path' => $this->sourcePath,
        ];
    }
}
