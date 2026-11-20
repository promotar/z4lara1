<?php

namespace Modules\Store\Domain\Catalog;

use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;

final class VariantAxisValidationService
{
    /**
     * @param  array<string, AttributeDefinition>  $assignedAxes
     * @param  array<string, string>  $optionByAttributeCode
     */
    public function validate(string $productTypeCode, array $assignedAxes, array $optionByAttributeCode): void
    {
        if ($productTypeCode === 'simple' && $assignedAxes !== []) {
            throw new InvalidVariantCombination('A simple product cannot define variant axes.');
        }

        foreach ($assignedAxes as $code => $definition) {
            if (! $definition->supportsVariantAxis || $definition->dataType !== AttributeDataType::Select) {
                throw new InvalidVariantCombination("Attribute [{$code}] is not a selectable variant axis.");
            }

            if (! array_key_exists($code, $optionByAttributeCode)) {
                throw new InvalidVariantCombination("Variant is missing axis [{$code}].");
            }
        }

        $extra = array_diff_key($optionByAttributeCode, $assignedAxes);
        if ($extra !== []) {
            throw new InvalidVariantCombination('Variant contains undeclared axis options.');
        }
    }
}
