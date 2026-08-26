<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
                Rule::unique('departments', 'code')->ignore($this->route('department')),
            ],
            'description' => ['nullable', 'string'],
        ];
    }
}
