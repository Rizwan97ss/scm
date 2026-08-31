<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GuardianImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return ['student_admission_number', 'first_name', 'last_name', 'email', 'phone', 'relationship_type', 'is_primary', 'can_pickup'];
    }

    public function array(): array
    {
        return [
            ['2026-0001', 'John', 'Doe', 'john.doe@example.com', '+1-555-0101', 'father', 'yes', 'yes'],
        ];
    }
}
