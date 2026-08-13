<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', new ValidName],
            'code' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('rooms', 'code')->ignore($this->route('room')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'type' => ['sometimes', Rule::in(['classroom', 'lab', 'hall', 'other'])],
        ];
    }
}
