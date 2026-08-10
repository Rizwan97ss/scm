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
        $schoolId = $this->user()->school_id;

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'category' => ['sometimes', Rule::in(array_column(RemarkCategory::cases(), 'value'))],
            'body' => ['required', 'string'],
            'visible_to_guardian' => ['sometimes', 'boolean'],
        ];
    }
}
