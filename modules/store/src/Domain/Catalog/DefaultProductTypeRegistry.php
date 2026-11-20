<?php

namespace Modules\Store\Domain\Catalog;

use LogicException;
use Modules\Store\Domain\Shared\Exceptions\UnknownProductType;

final class DefaultProductTypeRegistry implements ProductTypeRegistry
{
    /** @var array<string, ProductTypeDefinition> */
    private array $definitions = [];

    public function __construct()
    {
        $this->register(new ProductTypeDefinition('simple', false, true, true));
        $this->register(new ProductTypeDefinition('variant', true, true, true));
        $this->register(new ProductTypeDefinition('artwork', false, true, true));
    }

    public function register(ProductTypeDefinition $definition): void
    {
        if (isset($this->definitions[$definition->code])) {
            throw new LogicException("Product type [{$definition->code}] is already registered.");
        }

        $this->definitions[$definition->code] = $definition;
        ksort($this->definitions);
    }

    public function get(string $code): ProductTypeDefinition
    {
        return $this->definitions[$code]
            ?? throw new UnknownProductType("Unknown Store product type [{$code}].");
    }

    public function has(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    public function all(): array
    {
        return $this->definitions;
    }
}
