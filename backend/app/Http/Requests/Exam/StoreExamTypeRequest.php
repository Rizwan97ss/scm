<?php

namespace App\Http\Requests\Exam;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidName],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('exam_types', 'code')],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
