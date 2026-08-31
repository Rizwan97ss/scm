<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserImportTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    public function headings(): array
    {
        return [
            'first_name', 'last_name', 'email', 'role', 'phone', 'designation_name', 'employee_id', 'hire_date',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Jane', 'Doe', 'jane.doe@example.com', 'Teacher', '+1-555-0100', 'Senior Teacher', 'EMP-0142', now()->toDateString(),
            ],
        ];
    }
}
