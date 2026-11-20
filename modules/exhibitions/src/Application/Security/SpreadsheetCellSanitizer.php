<?php

namespace Modules\Exhibition\Application\Security;

final class SpreadsheetCellSanitizer
{
    public static function escape(mixed $value): string
    {
        $cell = (string) $value;

        if ($cell !== '' && preg_match('/^[\x00-\x20]*[=+\-@]/', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }

    /** @param array<int, mixed> $row @return array<int, string> */
    public static function row(array $row): array
    {
        return array_map(self::escape(...), $row);
    }
}
