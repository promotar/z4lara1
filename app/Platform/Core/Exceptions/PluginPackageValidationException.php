<?php

namespace App\Platform\Core\Exceptions;

use RuntimeException;

class PluginPackageValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(
            'Plugin package validation failed: '.collect($errors)
                ->values()
                ->map(fn (string $error, int $index): string => ($index + 1).') '.$error)
                ->implode(' '),
        );
    }
}
