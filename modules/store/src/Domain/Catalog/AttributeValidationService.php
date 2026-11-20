<?php

namespace Modules\Store\Domain\Catalog;

use Modules\Store\Domain\Shared\Exceptions\AttributeTypeMismatch;

final class AttributeValidationService
{
    /** @param list<string> $allowedOptions */
    public function validate(AttributeDefinition $definition, mixed $value, array $allowedOptions = []): AttributeValue
    {
        return match ($definition->dataType) {
            AttributeDataType::Text => $this->text($definition, $value),
            AttributeDataType::Integer => $this->integer($definition, $value),
            AttributeDataType::Decimal => $this->decimal($definition, $value),
            AttributeDataType::Boolean => $this->boolean($value),
            AttributeDataType::Select => $this->select($value, $allowedOptions),
        };
    }

    private function text(AttributeDefinition $definition, mixed $value): AttributeValue
    {
        if (! is_string($value)) {
            throw new AttributeTypeMismatch('Text attribute requires a string.');
        }

        $value = trim($value);
        $maximum = (int) ($definition->validation['max_length'] ?? 65_535);
        if (($definition->required && $value === '') || mb_strlen($value, 'UTF-8') > $maximum) {
            throw new AttributeTypeMismatch('Text attribute violates required or length validation.');
        }

        if (isset($definition->validation['pattern'])
            && preg_match((string) $definition->validation['pattern'], $value) !== 1) {
            throw new AttributeTypeMismatch('Text attribute does not match its registered pattern.');
        }

        return new AttributeValue(AttributeDataType::Text, $value);
    }

    private function integer(AttributeDefinition $definition, mixed $value): AttributeValue
    {
        if (! is_int($value)) {
            throw new AttributeTypeMismatch('Integer attribute requires an integer.');
        }

        $this->assertRange($definition, $value);

        return new AttributeValue(AttributeDataType::Integer, $value);
    }

    private function decimal(AttributeDefinition $definition, mixed $value): AttributeValue
    {
        if (! is_string($value) || ! preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?$/', trim($value))) {
            throw new AttributeTypeMismatch('Decimal attribute requires an exact decimal string with at most eight fractional digits.');
        }

        $value = trim($value);
        $numeric = (float) $value;
        $this->assertRange($definition, $numeric);

        return new AttributeValue(AttributeDataType::Decimal, $value);
    }

    private function boolean(mixed $value): AttributeValue
    {
        if (! is_bool($value)) {
            throw new AttributeTypeMismatch('Boolean attribute requires a boolean.');
        }

        return new AttributeValue(AttributeDataType::Boolean, $value);
    }

    /** @param list<string> $allowedOptions */
    private function select(mixed $value, array $allowedOptions): AttributeValue
    {
        if (! is_string($value) || ! in_array($value, $allowedOptions, true)) {
            throw new AttributeTypeMismatch('Select attribute requires an allowed option public ID.');
        }

        return new AttributeValue(AttributeDataType::Select, $value);
    }

    private function assertRange(AttributeDefinition $definition, int|float $value): void
    {
        if (isset($definition->validation['min']) && $value < $definition->validation['min']) {
            throw new AttributeTypeMismatch('Attribute value is below its minimum.');
        }

        if (isset($definition->validation['max']) && $value > $definition->validation['max']) {
            throw new AttributeTypeMismatch('Attribute value is above its maximum.');
        }
    }
}
