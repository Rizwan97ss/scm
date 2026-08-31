<?php

namespace App\Imports;

use App\Imports\Concerns\SimpleLookupImport;
use App\Models\Department;
use App\Rules\ValidName;
use Illuminate\Support\Collection;

class DepartmentsImport extends SimpleLookupImport
{
    protected function uniqueKeyField(): string
    {
        return 'code';
    }

    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function mapRow(Collection $data, int $rowIndex): ?array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidName],
            'code' => ['required', 'string', 'max:20', ...$this->uniqueRuleUnlessUpdating('departments', 'code')],
            'description' => ['nullable', 'string'],
        ];
    }
}
