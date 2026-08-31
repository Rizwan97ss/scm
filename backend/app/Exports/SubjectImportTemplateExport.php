<?php

namespace App\Exports;

use App\Exports\Support\ReferenceSheet;
use App\Models\Department;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SubjectImportTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        $departments = Department::query()->orderBy('name')->get(['code', 'name']);

        return [
            new ReferenceSheet('Subjects', ['name', 'code', 'department_code', 'is_elective'], [
                ['Mathematics', 'MATH', 'MATSCI', 'no'],
            ]),
            new ReferenceSheet(
                'Valid Department Codes',
                ['code', 'name'],
                $departments->map(fn ($d) => [$d->code, $d->name])->all(),
            ),
        ];
    }
}
