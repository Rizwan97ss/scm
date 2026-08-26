<?php

namespace App\Support;

/**
 * Excel/LibreOffice/Sheets all treat a cell whose text starts with
 * =, +, -, or @ as a formula, regardless of file format (csv, xlsx) —
 * "CSV/formula injection". Every export in this app pulls in
 * user-controllable free text (guardian names, remarks, emergency
 * contacts, ...) with no way to know at write time whether a given string
 * is safe, so every exported cell goes through here rather than trying to
 * allowlist which fields are "risky". Prefixing with a tab is enough to
 * stop the leading character being read as a formula operator while
 * staying visually invisible in the opened sheet (a leading apostrophe
 * would itself become part of the visible cell content in some viewers).
 */
class SpreadsheetSanitizer
{
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function value(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::FORMULA_PREFIXES, true) ? "\t{$value}" : $value;
    }

    /** @param array<int|string, mixed> $row @return array<int|string, mixed> */
    public static function row(array $row): array
    {
        return array_map(self::value(...), $row);
    }
}
