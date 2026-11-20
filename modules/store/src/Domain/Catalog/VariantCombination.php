<?php

namespace Modules\Store\Domain\Catalog;

use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;

final readonly class VariantCombination
{
    /** @var array<string, string> */
    public array $options;

    /** @param array<string, string> $options */
    public function __construct(array $options)
    {
        $normalized = [];

        foreach ($options as $attribute => $option) {
            $attribute = strtolower(trim((string) $attribute));
            $option = strtolower(trim((string) $option));

            if (! preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $attribute)
                || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $option)) {
                throw new InvalidVariantCombination('Variant combination contains an invalid attribute or option code.');
            }

            if (isset($normalized[$attribute])) {
                throw new InvalidVariantCombination("Variant combination repeats attribute [{$attribute}].");
            }

            $normalized[$attribute] = $option;
        }

        ksort($normalized);
        $this->options = $normalized;
    }

    public function key(): string
    {
        if ($this->options === []) {
            return '__default__';
        }

        $pairs = [];
        foreach ($this->options as $attribute => $option) {
            $pairs[] = "{$attribute}={$option}";
        }

        return implode('|', $pairs);
    }
}
