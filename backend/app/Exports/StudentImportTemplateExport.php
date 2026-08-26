<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return [
            'first_name', 'last_name', 'gender', 'date_of_birth', 'grade_level_code', 'section_name', 'department_code',
            'roll_number', 'admission_date', 'blood_group', 'nationality', 'previous_school_name',
            'emergency_contact_name', 'emergency_contact_phone',
        ];
    }

    public function array(): array
    {
        return [
            ['Jane', 'Doe', 'female', '2018-05-14', 'G1', 'A', '', '', now()->toDateString(), 'O+', '', '', 'Parent Name', '+1-555-0100'],
        ];
    }
}
