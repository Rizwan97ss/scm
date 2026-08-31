<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * A second sheet on an import template listing real, current valid values
 * (department codes, grade level codes, ...) for a column the main sheet
 * expects a code in — the "Reference Values" sheet from the import-center
 * spec, minus the dynamic-per-institution-config machinery that would need
 * the (not-yet-built) Custom Fields/Terminology engines. This part doesn't:
 * it just queries the DB fresh on every template download, so it's never
 * stale the way a hardcoded example row would be.
 */
class ReferenceSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
