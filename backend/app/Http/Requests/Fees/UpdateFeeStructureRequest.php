<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'academic_year_id' => ['sometimes', 'required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'grade_level_id' => ['nullable', Rule::exists('grade_levels', 'id')->where('school_id', $schoolId)],
            'fee_category_id' => ['sometimes', 'required', Rule::exists('fee_categories', 'id')->where('school_id', $schoolId)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'frequency' => ['sometimes', 'required', Rule::in(['one_time', 'monthly', 'quarterly', 'term', 'annual'])],
            'due_day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
