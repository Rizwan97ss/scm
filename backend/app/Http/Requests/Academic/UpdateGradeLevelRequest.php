<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'code' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('grade_levels', 'code')->where('school_id', $this->user()->school_id)->ignore($this->route('gradeLevel')),
            ],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
