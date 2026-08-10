<?php

namespace App\Http\Requests\Academic;

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
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->where('school_id', $this->user()->school_id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('school_id', $this->user()->school_id)],
            'is_elective' => ['sometimes', 'boolean'],
        ];
    }
}
