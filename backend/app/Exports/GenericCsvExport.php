<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * One reusable CSV sheet for DataExportService — every dataset it produces
 * (account, student profile, invoices, payments, ...) is just headings +
 * rows-as-arrays, so a single parameterized class covers all of them
 * instead of a near-identical Export class per resource (the shape
 * StudentsExport uses for its one, richer, filterable/sortable export).
 */
class GenericCsvExport implements FromCollection, WithHeadings
{
    /**
     * @param  array<int, string>  $headings
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    public function __construct(private readonly array $headings, private readonly Collection $rows) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }
}
