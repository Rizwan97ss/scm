<?php

namespace App\Imports;

use App\Imports\Concerns\SimpleLookupImport;
use App\Models\GradeLevel;
use App\Rules\ValidName;
use Illuminate\Support\Collection;

class GradeLevelsImport extends SimpleLookupImport
{
    protected function uniqueKeyField(): string
    {
        return 'code';
    }

    protected function modelClass(): string
    {
        return GradeLevel::class;
    }

    protected function mapRow(Collection $data, int $rowIndex): ?array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'sequence' => isset($data['sequence']) && $data['sequence'] !== '' ? (int) $data['sequence'] : 0,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', new ValidName],
            'code' => ['required', 'string', 'max:20', ...$this->uniqueRuleUnlessUpdating('grade_levels', 'code')],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
