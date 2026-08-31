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
            'emergency_contact_name', 'emergency_contact_phone', 'address_line1', 'city',
            'guardian1_first_name', 'guardian1_last_name', 'guardian1_email', 'guardian1_phone',
            'guardian1_relationship', 'guardian1_is_primary', 'guardian1_can_pickup',
            'guardian2_first_name', 'guardian2_last_name', 'guardian2_email', 'guardian2_phone',
            'guardian2_relationship', 'guardian2_is_primary', 'guardian2_can_pickup',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Jane', 'Doe', 'female', '2018-05-14', 'G1', 'A', '',
                '', now()->toDateString(), 'O+', '', '',
                'Parent Name', '+1-555-0100', '123 Riverside Avenue', 'Springfield',
                'John', 'Doe', 'john.doe@example.com', '+1-555-0101',
                'father', 'yes', 'yes',
                'Mary', 'Doe', 'mary.doe@example.com', '+1-555-0102',
                'mother', 'no', 'yes',
            ],
        ];
    }
}
