<?php

namespace Modules\Store\Domain\Catalog;

use Modules\Store\Domain\Shared\Exceptions\InvalidVariantCombination;

final readonly class VariantMatrixGenerator
{
    public function __construct(private int $maximumVariants = 100) {}

    /**
     * @param  array<string, list<string>>  $axes
     * @return list<VariantCombination>
     */
    public function generate(array $axes): array
    {
        if ($axes === []) {
            throw new InvalidVariantCombination('Variant matrix requires at least one axis.');
        }

        ksort($axes);
        $combinations = [[]];

        foreach ($axes as $attribute => $options) {
            $options = array_values(array_unique(array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $options,
            )));
            sort($options);

            if ($options === []) {
                throw new InvalidVariantCombination("Variant axis [{$attribute}] has no options.");
            }

            $next = [];
            foreach ($combinations as $combination) {
                foreach ($options as $option) {
                    $next[] = [...$combination, $attribute => $option];

                    if (count($next) > $this->maximumVariants) {
                        throw new InvalidVariantCombination("Variant matrix exceeds the {$this->maximumVariants} variant limit.");
                    }
                }
            }

            $combinations = $next;
        }

        return array_map(static fn (array $options): VariantCombination => new VariantCombination($options), $combinations);
    }
}
