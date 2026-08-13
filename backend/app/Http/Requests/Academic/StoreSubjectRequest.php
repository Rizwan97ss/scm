<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidName],
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'is_elective' => ['sometimes', 'boolean'],
        ];
    }
}
