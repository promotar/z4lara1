<?php

namespace Modules\Store\Infrastructure\Persistence\Catalog;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Store\Domain\Catalog\AssignedAttribute;
use Modules\Store\Domain\Catalog\AttributeDataType;
use Modules\Store\Domain\Catalog\AttributeDefinition;
use Modules\Store\Domain\Catalog\AttributeRecord;
use Modules\Store\Domain\Catalog\AttributeValue;
use Modules\Store\Domain\Catalog\ProductAttributeRepository;
use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;

final class DatabaseProductAttributeRepository implements ProductAttributeRepository
{
    public function createDefinition(
        string $publicId,
        string $code,
        AttributeDataType $dataType,
        bool $filterable,
        bool $visible,
        bool $supportsVariantAxis,
        array $validation,
        DateTimeImmutable $now,
    ): int {
        return (int) DB::table('store_attributes')->insertGetId([
            'public_id' => $publicId,
            'code' => $code,
            'data_type' => $dataType->value,
            'status' => 'active',
            'is_filterable' => $filterable,
            'is_visible' => $visible,
            'supports_variant_axis' => $supportsVariantAxis,
            'position' => 0,
            'validation_schema' => $validation === [] ? null : json_encode($validation, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addDefinitionTranslation(int $attributeId, string $locale, string $name, DateTimeImmutable $now): void
    {
        DB::table('store_attribute_translations')->insert([
            'attribute_id' => $attributeId,
            'locale' => $locale,
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findByPublicId(string $publicId): ?AttributeRecord
    {
        $row = DB::table('store_attributes')->where('public_id', $publicId)->first();

        return $row === null ? null : $this->record($row);
    }

    public function findActiveVariantAxisByName(string $locale, string $name): ?AttributeRecord
    {
        $row = DB::table('store_attributes as attribute')
            ->join('store_attribute_translations as translation', function ($join) use ($locale): void {
                $join->on('translation.attribute_id', '=', 'attribute.id')
                    ->where('translation.locale', '=', $locale);
            })
            ->where('attribute.status', 'active')
            ->where('attribute.data_type', AttributeDataType::Select->value)
            ->where('attribute.supports_variant_axis', true)
            ->where('translation.name', $name)
            ->first(['attribute.*']);

        return $row === null ? null : $this->record($row);
    }

    public function definitionCodeExists(string $code): bool
    {
        return DB::table('store_attributes')->where('code', $code)->exists();
    }

    public function createOption(string $publicId, int $attributeId, string $code, int $position, DateTimeImmutable $now): int
    {
        return (int) DB::table('store_attribute_options')->insertGetId([
            'public_id' => $publicId,
            'attribute_id' => $attributeId,
            'code' => $code,
            'status' => 'active',
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addOptionTranslation(int $optionId, string $locale, string $name, DateTimeImmutable $now): void
    {
        DB::table('store_attribute_option_translations')->insert([
            'attribute_option_id' => $optionId,
            'locale' => $locale,
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findActiveOptionByName(int $attributeId, string $locale, string $name): ?array
    {
        $row = DB::table('store_attribute_options as option')
            ->join('store_attribute_option_translations as translation', function ($join) use ($locale): void {
                $join->on('translation.attribute_option_id', '=', 'option.id')
                    ->where('translation.locale', '=', $locale);
            })
            ->where('option.attribute_id', $attributeId)
            ->where('option.status', 'active')
            ->where('translation.name', $name)
            ->first(['option.public_id', 'option.code']);

        return $row === null ? null : [
            'public_id' => (string) $row->public_id,
            'code' => (string) $row->code,
        ];
    }

    public function optionCodeExists(int $attributeId, string $code): bool
    {
        return DB::table('store_attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('code', $code)
            ->exists();
    }

    public function assign(
        int $productId,
        int $attributeId,
        bool $required,
        bool $variantAxis,
        bool $filterable,
        bool $visible,
        int $position,
        DateTimeImmutable $now,
    ): void {
        DB::table('store_product_attribute_assignments')->updateOrInsert(
            ['product_id' => $productId, 'attribute_id' => $attributeId],
            [
                'is_required' => $required,
                'is_variant_axis' => $variantAxis,
                'is_filterable' => $filterable,
                'is_visible' => $visible,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function assigned(int $productId, string $attributePublicId): ?AssignedAttribute
    {
        $row = DB::table('store_product_attribute_assignments as assignments')
            ->join('store_attributes as attributes', 'attributes.id', '=', 'assignments.attribute_id')
            ->where('assignments.product_id', $productId)
            ->where('attributes.public_id', $attributePublicId)
            ->first([
                'attributes.*',
                'assignments.is_required as assignment_required',
                'assignments.is_variant_axis as assignment_variant_axis',
            ]);

        return $row === null ? null : new AssignedAttribute(
            $this->record($row),
            (bool) $row->assignment_required,
            (bool) $row->assignment_variant_axis,
        );
    }

    public function allowedOptionPublicIds(int $attributeId): array
    {
        return DB::table('store_attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('status', 'active')
            ->orderBy('position')
            ->pluck('public_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function optionIdByPublicId(int $attributeId, string $optionPublicId): ?int
    {
        $id = DB::table('store_attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('public_id', $optionPublicId)
            ->where('status', 'active')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function upsertProductValue(int $productId, int $attributeId, AttributeValue $value, ?int $optionId, DateTimeImmutable $now): void
    {
        $values = [
            'option_id' => null,
            'value_text' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'updated_at' => $now,
        ];
        match ($value->type) {
            AttributeDataType::Text => $values['value_text'] = $value->value,
            AttributeDataType::Integer => $values['value_integer'] = $value->value,
            AttributeDataType::Decimal => $values['value_decimal'] = $value->value,
            AttributeDataType::Boolean => $values['value_boolean'] = $value->value,
            AttributeDataType::Select => $values['option_id'] = $optionId
                ?? throw new InvalidArgumentException('Selected attribute option does not exist.'),
        };

        DB::table('store_product_attribute_values')->updateOrInsert(
            ['product_id' => $productId, 'attribute_id' => $attributeId],
            ['created_at' => $now, ...$values],
        );
    }

    public function resolveVariantAxisOptions(int $productId, array $optionCodeByAttributeCode): array
    {
        $axes = DB::table('store_product_attribute_assignments as assignments')
            ->join('store_attributes as attributes', 'attributes.id', '=', 'assignments.attribute_id')
            ->where('assignments.product_id', $productId)
            ->where('assignments.is_variant_axis', true)
            ->where('attributes.status', 'active')
            ->get(['attributes.id', 'attributes.code']);
        $expectedCodes = $axes->pluck('code')->map(static fn (mixed $code): string => (string) $code)->sort()->values()->all();
        $actualCodes = array_keys($optionCodeByAttributeCode);
        sort($actualCodes);
        if ($expectedCodes !== $actualCodes) {
            throw new InvalidVariantCombination('Variant option codes must exactly match the product variant axes.');
        }

        $resolved = [];
        foreach ($axes as $axis) {
            $optionId = DB::table('store_attribute_options')
                ->where('attribute_id', $axis->id)
                ->where('code', $optionCodeByAttributeCode[(string) $axis->code])
                ->where('status', 'active')
                ->value('id');
            if ($optionId === null) {
                throw new InvalidVariantCombination("Unknown option for variant axis [{$axis->code}].");
            }
            $resolved[] = ['attribute_id' => (int) $axis->id, 'option_id' => (int) $optionId];
        }

        return $resolved;
    }

    public function attachVariantOptions(int $variantId, array $options, DateTimeImmutable $now): void
    {
        foreach ($options as $option) {
            DB::table('store_variant_option_values')->insert([
                'variant_id' => $variantId,
                'attribute_id' => $option['attribute_id'],
                'attribute_option_id' => $option['option_id'],
                'created_at' => $now,
            ]);
        }
    }

    private function record(object $row): AttributeRecord
    {
        $validation = $row->validation_schema === null
            ? []
            : json_decode((string) $row->validation_schema, true, flags: JSON_THROW_ON_ERROR);

        return new AttributeRecord(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            definition: new AttributeDefinition(
                code: (string) $row->code,
                dataType: AttributeDataType::from((string) $row->data_type),
                supportsVariantAxis: (bool) $row->supports_variant_axis,
                validation: $validation,
            ),
        );
    }

    public function updateDefinitionStatus(int $attributeId, string $status, DateTimeImmutable $now): void
    {
        DB::table('store_attributes')->where('id', $attributeId)->update([
            'status' => $status,
            'archived_at' => $status === 'archived' ? $now : null,
            'updated_at' => $now,
        ]);
    }

    public function updateOptionStatus(int $attributeId, string $optionPublicId, string $status, DateTimeImmutable $now): bool
    {
        return DB::table('store_attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('public_id', $optionPublicId)
            ->update([
                'status' => $status,
                'archived_at' => $status === 'archived' ? $now : null,
                'updated_at' => $now,
            ]) === 1;
    }

    public function activeAssignmentCount(int $attributeId): int
    {
        return DB::table('store_product_attribute_assignments as assignment')
            ->join('store_products as product', 'product.id', '=', 'assignment.product_id')
            ->where('assignment.attribute_id', $attributeId)
            ->where('product.status', 'published')
            ->count();
    }
}
