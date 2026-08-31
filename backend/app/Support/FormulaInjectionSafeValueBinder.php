<?php

namespace App\Support;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

/**
 * Neutralizes CSV/Excel "formula injection" (OWASP CSV Injection) on every
 * spreadsheet export app-wide. A cell value beginning with =, +, -, @, a
 * tab, or a carriage return is interpreted as a live formula the instant a
 * human opens the downloaded file in Excel/Sheets/LibreOffice — e.g. a
 * student name imported (or just typed into a form) as
 * `=cmd|'/c calc'!A1` or a DDE payload, later re-exported verbatim in the
 * student roster/report-card/data-export download and executed by whoever
 * opens it.
 *
 * Registered globally via config/excel.php's value_binder.default rather
 * than sanitizing per-Export-class, so every current export (StudentsExport,
 * GenericCsvExport's GDPR data-export pipeline, import templates, ...) and
 * every future one is covered automatically — nobody has to remember to
 * call a sanitizer helper when adding the next Export class.
 *
 * Mitigation is OWASP's standard one: prefix a leading single quote, which
 * both re-types the cell as text (PhpSpreadsheet's own formula detection
 * only fires when a string literally starts with "=") and makes the
 * neutralization visible rather than silently dropping/altering the rest
 * of the value.
 */
class FormulaInjectionSafeValueBinder extends DefaultValueBinder
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && $value !== '' && in_array($value[0], self::DANGEROUS_PREFIXES, true)) {
            $value = "'".$value;
        }

        return parent::bindValue($cell, $value);
    }
}
