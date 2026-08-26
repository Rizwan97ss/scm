<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExamMarkImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return ['admission_number', 'marks_obtained', 'is_absent', 'remarks'];
    }

    public function array(): array
    {
        return [
            ['2026-0001', 48, '', ''],
            ['2026-0002', '', 'true', 'Medical leave'],
        ];
    }
}
