<?php

namespace Modules\Store\Domain\Catalog;

use DateTimeImmutable;

interface ProductAttributeRepository
{
    /** @param array<string, mixed> $validation */
    public function createDefinition(
        string $publicId,
        string $code,
        AttributeDataType $dataType,
        bool $filterable,
        bool $visible,
        bool $supportsVariantAxis,
        array $validation,
        DateTimeImmutable $now,
    ): int;

    public function addDefinitionTranslation(int $attributeId, string $locale, string $name, DateTimeImmutable $now): void;

    public function findByPublicId(string $publicId): ?AttributeRecord;

    public function findActiveVariantAxisByName(string $locale, string $name): ?AttributeRecord;

    public function definitionCodeExists(string $code): bool;

    public function createOption(
        string $publicId,
        int $attributeId,
        string $code,
        int $position,
        DateTimeImmutable $now,
    ): int;

    public function addOptionTranslation(int $optionId, string $locale, string $name, DateTimeImmutable $now): void;

    /** @return array{public_id: string, code: string}|null */
    public function findActiveOptionByName(int $attributeId, string $locale, string $name): ?array;

    public function optionCodeExists(int $attributeId, string $code): bool;

    public function assign(
        int $productId,
        int $attributeId,
        bool $required,
        bool $variantAxis,
        bool $filterable,
        bool $visible,
        int $position,
        DateTimeImmutable $now,
    ): void;

    public function assigned(int $productId, string $attributePublicId): ?AssignedAttribute;

    /** @return list<string> */
    public function allowedOptionPublicIds(int $attributeId): array;

    public function optionIdByPublicId(int $attributeId, string $optionPublicId): ?int;

    public function upsertProductValue(int $productId, int $attributeId, AttributeValue $value, ?int $optionId, DateTimeImmutable $now): void;

    /**
     * @param  array<string, string>  $optionCodeByAttributeCode
     * @return list<array{attribute_id: int, option_id: int}>
     */
    public function resolveVariantAxisOptions(int $productId, array $optionCodeByAttributeCode): array;

    /** @param list<array{attribute_id: int, option_id: int}> $options */
    public function attachVariantOptions(int $variantId, array $options, DateTimeImmutable $now): void;

    public function updateDefinitionStatus(int $attributeId, string $status, DateTimeImmutable $now): void;

    public function updateOptionStatus(int $attributeId, string $optionPublicId, string $status, DateTimeImmutable $now): bool;

    public function activeAssignmentCount(int $attributeId): int;
}
