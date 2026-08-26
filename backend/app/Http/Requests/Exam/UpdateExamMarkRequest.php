<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamMarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'is_absent' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
