<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['roles', 'designation']);
    }

    public function headings(): array
    {
        return ['First Name', 'Last Name', 'Email', 'Roles', 'Designation', 'Employee ID', 'Status'];
    }

    public function map($user): array
    {
        return [
            $user->first_name,
            $user->last_name,
            $user->email,
            $user->roles->pluck('name')->implode(', '),
            $user->designation?->name,
            $user->employee_id,
            $user->status?->value,
        ];
    }
}
