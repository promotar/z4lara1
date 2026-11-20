<?php

namespace Modules\Store\Application\Catalog;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Store\Domain\Catalog\AttributeDataType;
use Modules\Store\Domain\Catalog\AttributeDefinition;
use Modules\Store\Domain\Catalog\AttributeValidationService;
use Modules\Store\Domain\Catalog\ProductAttributeRepository;
use Modules\Store\Domain\Catalog\ProductRepository;
use Modules\Store\Domain\Catalog\ProductTypeRegistry;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;
use Modules\Store\Domain\Shared\Exceptions\ProductNotFound;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductAttributeService
{
    public function __construct(
        private ProductAttributeRepository $attributes,
        private ProductRepository $products,
        private ProductTypeRegistry $types,
        private AttributeValidationService $validation,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /** @param array<string, mixed> $validation */
    public function createDefinition(
        string $code,
        AttributeDataType $dataType,
        string $locale,
        string $name,
        bool $filterable = false,
        bool $visible = true,
        bool $supportsVariantAxis = false,
        array $validation = [],
    ): string {
        $definition = new AttributeDefinition($code, $dataType, false, $supportsVariantAxis, $validation);
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Attribute name is required.');
        }
        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        $this->transactions->run(function () use ($definition, $locale, $name, $filterable, $visible, $publicId, $now): void {
            $id = $this->attributes->createDefinition(
                $publicId,
                $definition->code,
                $definition->dataType,
                $filterable,
                $visible,
                $definition->supportsVariantAxis,
                $definition->validation,
                $now,
            );
            $this->attributes->addDefinitionTranslation($id, $locale, $name, $now);
        });

        return $publicId;
    }

    public function addOption(string $attributePublicId, string $code, string $locale, string $name, int $position = 0): string
    {
        $attribute = $this->attributes->findByPublicId($attributePublicId)
            ?? throw new InvalidArgumentException('Attribute does not exist.');
        if ($attribute->definition->dataType !== AttributeDataType::Select) {
            throw new InvalidArgumentException('Only select attributes can have options.');
        }
        $code = strtolower(trim($code));
        $name = trim($name);
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/', $code) || $name === '') {
            throw new InvalidArgumentException('Attribute option code or name is invalid.');
        }
        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();
        $this->transactions->run(function () use ($publicId, $attribute, $code, $locale, $name, $position, $now): void {
            $id = $this->attributes->createOption($publicId, $attribute->id, $code, max(0, $position), $now);
            $this->attributes->addOptionTranslation($id, $locale, $name, $now);
        });

        return $publicId;
    }

    /**
     * @return array{id: int, public_id: string, code: string}
     */
    public function resolveVariantAttribute(string $locale, string $name): array
    {
        $existing = $this->attributes->findActiveVariantAxisByName($locale, $name);
        if ($existing !== null) {
            return [
                'id' => $existing->id,
                'public_id' => $existing->publicId,
                'code' => $existing->definition->code,
            ];
        }

        $baseCode = $this->catalogCode($name, 'attribute');
        $code = $baseCode;
        $suffix = 2;
        while ($this->attributes->definitionCodeExists($code)) {
            $code = substr($baseCode, 0, 124)."_{$suffix}";
            $suffix++;
        }
        $publicId = $this->createDefinition(
            $code,
            AttributeDataType::Select,
            $locale,
            $name,
            filterable: false,
            visible: true,
            supportsVariantAxis: true,
        );
        $created = $this->attributes->findByPublicId($publicId)
            ?? throw new InvalidArgumentException('Created attribute could not be loaded.');

        return ['id' => $created->id, 'public_id' => $publicId, 'code' => $code];
    }

    /**
     * @param  array{id: int, public_id: string, code: string}  $attribute
     * @return array{public_id: string, code: string}
     */
    public function resolveVariantOption(
        string $locale,
        array $attribute,
        string $name,
        int $position,
    ): array {
        $existing = $this->attributes->findActiveOptionByName($attribute['id'], $locale, $name);
        if ($existing !== null) {
            return $existing;
        }

        $baseCode = $this->catalogCode($name, 'value');
        $code = $baseCode;
        $suffix = 2;
        while ($this->attributes->optionCodeExists($attribute['id'], $code)) {
            $code = substr($baseCode, 0, 124)."_{$suffix}";
            $suffix++;
        }

        return [
            'public_id' => $this->addOption($attribute['public_id'], $code, $locale, $name, $position),
            'code' => $code,
        ];
    }

    public function assignToProduct(
        string $productPublicId,
        string $attributePublicId,
        bool $required,
        bool $variantAxis = false,
        bool $filterable = false,
        bool $visible = true,
        int $position = 0,
    ): void {
        $now = $this->clock->now();
        $this->transactions->run(function () use (
            $productPublicId, $attributePublicId, $required, $variantAxis, $filterable, $visible, $position, $now,
        ): void {
            $product = $this->products->findByPublicId($productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            $attribute = $this->attributes->findByPublicId($attributePublicId)
                ?? throw new InvalidArgumentException('Attribute does not exist.');
            if ($variantAxis && (! $this->types->get($product->typeCode)->supportsVariants
                || ! $attribute->definition->supportsVariantAxis)) {
                throw new InvalidVariantCombination('Product type or attribute does not support a variant axis.');
            }
            $this->attributes->assign(
                $product->id,
                $attribute->id,
                $required,
                $variantAxis,
                $filterable,
                $visible,
                max(0, $position),
                $now,
            );
        });
    }

    public function setProductValue(string $productPublicId, string $attributePublicId, mixed $rawValue): void
    {
        $now = $this->clock->now();
        $this->transactions->run(function () use ($productPublicId, $attributePublicId, $rawValue, $now): void {
            $product = $this->products->findByPublicId($productPublicId, true)
                ?? throw new ProductNotFound('Product was not found.');
            $assigned = $this->attributes->assigned($product->id, $attributePublicId)
                ?? throw new InvalidArgumentException('Attribute is not assigned to this product.');
            $definition = new AttributeDefinition(
                $assigned->attribute->definition->code,
                $assigned->attribute->definition->dataType,
                $assigned->required,
                $assigned->attribute->definition->supportsVariantAxis,
                $assigned->attribute->definition->validation,
            );
            $allowed = $this->attributes->allowedOptionPublicIds($assigned->attribute->id);
            $value = $this->validation->validate($definition, $rawValue, $allowed);
            $optionId = $definition->dataType === AttributeDataType::Select
                ? $this->attributes->optionIdByPublicId($assigned->attribute->id, (string) $value->value)
                : null;
            $this->attributes->upsertProductValue($product->id, $assigned->attribute->id, $value, $optionId, $now);
        });
    }

    public function setDefinitionStatus(string $attributePublicId, string $status): void
    {
        if (! in_array($status, ['active', 'inactive', 'archived'], true)) {
            throw new InvalidArgumentException('Attribute status is invalid.');
        }
        $attribute = $this->attributes->findByPublicId($attributePublicId)
            ?? throw new InvalidArgumentException('Attribute does not exist.');
        if ($status === 'archived' && $this->attributes->activeAssignmentCount($attribute->id) > 0) {
            throw new InvalidArgumentException('An attribute used by published products cannot be archived.');
        }
        $this->transactions->run(fn (): mixed => $this->attributes->updateDefinitionStatus($attribute->id, $status, $this->clock->now()));
    }

    public function setOptionStatus(string $attributePublicId, string $optionPublicId, string $status): void
    {
        if (! in_array($status, ['active', 'inactive', 'archived'], true)) {
            throw new InvalidArgumentException('Attribute option status is invalid.');
        }
        $attribute = $this->attributes->findByPublicId($attributePublicId)
            ?? throw new InvalidArgumentException('Attribute does not exist.');
        $updated = $this->transactions->run(fn (): bool => $this->attributes->updateOptionStatus(
            $attribute->id,
            $optionPublicId,
            $status,
            $this->clock->now(),
        ));
        if (! $updated) {
            throw new InvalidArgumentException('Attribute option does not exist.');
        }
    }

    private function catalogCode(string $value, string $prefix): string
    {
        $slug = str_replace('-', '_', Str::slug($value, '_'));
        $slug = strtolower((string) preg_replace('/[^a-z0-9_]+/', '_', $slug));
        $slug = trim($slug, '_');
        if ($slug === '' || ! preg_match('/^[a-z]/', $slug)) {
            $slug = $prefix.'_'.substr(sha1(mb_strtolower($value)), 0, 12);
        }

        return substr($slug, 0, 128);
    }
}
