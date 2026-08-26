<?php

namespace App\Http\Requests\Exam;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $examType = $this->route('exam_type');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', new ValidName],
            'code' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('exam_types', 'code')->ignore($examType)],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
