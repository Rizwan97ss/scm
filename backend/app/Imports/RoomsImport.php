<?php

namespace App\Imports;

use App\Imports\Concerns\SimpleLookupImport;
use App\Models\Room;
use App\Rules\ValidName;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class RoomsImport extends SimpleLookupImport
{
    protected function uniqueKeyField(): string
    {
        return 'code';
    }

    protected function modelClass(): string
    {
        return Room::class;
    }

    protected function mapRow(Collection $data, int $rowIndex): ?array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'capacity' => isset($data['capacity']) && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
            'type' => $data['type'] !== '' ? mb_strtolower(trim((string) $data['type'])) : 'classroom',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidName],
            'code' => ['required', 'string', 'max:20', ...$this->uniqueRuleUnlessUpdating('rooms', 'code')],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::in(['classroom', 'lab', 'hall', 'other', ''])],
        ];
    }
}
