<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DepartmentImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return ['name', 'code', 'description'];
    }

    public function array(): array
    {
        return [
            ['Mathematics & Science', 'MATSCI', 'Math, physics, chemistry, biology'],
        ];
    }
}
