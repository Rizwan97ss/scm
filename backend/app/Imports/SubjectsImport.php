<?php

namespace App\Imports;

use App\Imports\Concerns\SimpleLookupImport;
use App\Models\Department;
use App\Models\Subject;
use App\Rules\ValidName;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Validators\Failure;

class SubjectsImport extends SimpleLookupImport
{
    protected function uniqueKeyField(): string
    {
        return 'code';
    }

    protected function modelClass(): string
    {
        return Subject::class;
    }

    protected function mapRow(Collection $data, int $rowIndex): ?array
    {
        $departmentId = null;
        $departmentCode = trim((string) ($data['department_code'] ?? ''));

        if ($departmentCode !== '') {
            $department = Department::query()->where('code', $departmentCode)->first();

            if (! $department) {
                $this->failures[] = new Failure(
                    $rowIndex, 'department_code',
                    ["No department with code \"{$departmentCode}\" was found."],
                    $data->toArray(),
                );

                return null;
            }

            $departmentId = $department->id;
        }

        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'department_id' => $departmentId,
            'is_elective' => in_array(mb_strtolower(trim((string) ($data['is_elective'] ?? ''))), ['1', 'yes', 'y', 'true'], true),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidName],
            'code' => ['required', 'string', 'max:20', ...$this->uniqueRuleUnlessUpdating('subjects', 'code')],
            'department_code' => ['nullable', 'string', 'max:20'],
            'is_elective' => ['nullable'],
        ];
    }
}
