<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidName],
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')],
            'description' => ['nullable', 'string'],
        ];
    }
}
