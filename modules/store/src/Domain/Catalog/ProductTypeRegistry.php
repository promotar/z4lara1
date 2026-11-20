<?php

namespace Modules\Store\Domain\Catalog;

interface ProductTypeRegistry
{
    public function register(ProductTypeDefinition $definition): void;

    public function get(string $code): ProductTypeDefinition;

    public function has(string $code): bool;

    /** @return array<string, ProductTypeDefinition> */
    public function all(): array;
}
