<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['currentGradeLevel', 'currentSection']);
    }

    public function headings(): array
    {
        return [
            'Admission Number', 'First Name', 'Last Name', 'Gender', 'Date of Birth',
            'Grade Level', 'Section', 'Roll Number', 'Admission Date', 'Status',
            'Emergency Contact Name', 'Emergency Contact Phone',
        ];
    }

    public function map($student): array
    {
        return [
            $student->admission_number,
            $student->first_name,
            $student->last_name,
            $student->gender?->value,
            $student->date_of_birth?->toDateString(),
            $student->currentGradeLevel?->name,
            $student->currentSection?->name,
            $student->roll_number,
            $student->admission_date?->toDateString(),
            $student->status?->value,
            $student->emergency_contact_name,
            $student->emergency_contact_phone,
        ];
    }
}
