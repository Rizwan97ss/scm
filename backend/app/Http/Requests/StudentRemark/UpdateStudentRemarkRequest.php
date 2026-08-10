<?php

namespace App\Http\Requests\StudentRemark;

use App\Enums\RemarkCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRemarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', Rule::in(array_column(RemarkCategory::cases(), 'value'))],
            'body' => ['required', 'string'],
            'visible_to_guardian' => ['sometimes', 'boolean'],
        ];
    }
}
