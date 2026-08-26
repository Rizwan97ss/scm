<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
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
            'name' => ['sometimes', 'required', 'string', 'max:50', new ValidName],
            'code' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('grade_levels', 'code')->ignore($this->route('gradeLevel')),
            ],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
