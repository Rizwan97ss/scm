<?php

namespace App\Http\Requests\StudentRemark;

use App\Enums\RemarkCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRemarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'student_id' => ['required', Rule::exists('students', 'id')],
            'category' => ['sometimes', Rule::in(array_column(RemarkCategory::cases(), 'value'))],
            'body' => ['required', 'string'],
            'visible_to_guardian' => ['sometimes', 'boolean'],
        ];
    }
}
