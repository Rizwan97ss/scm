<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
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
                Rule::unique('subjects', 'code')->ignore($this->route('subject')),
            ],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'is_elective' => ['sometimes', 'boolean'],
        ];
    }
}
