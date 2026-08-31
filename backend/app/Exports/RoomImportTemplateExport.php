<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RoomImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return ['name', 'code', 'capacity', 'type'];
    }

    public function array(): array
    {
        return [
            ['Room 101', 'R101', '30', 'classroom'],
        ];
    }
}
