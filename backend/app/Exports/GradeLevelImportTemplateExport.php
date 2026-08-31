<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GradeLevelImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return ['name', 'code', 'sequence'];
    }

    public function array(): array
    {
        return [
            ['Grade 1', 'G1', '1'],
        ];
    }
}
