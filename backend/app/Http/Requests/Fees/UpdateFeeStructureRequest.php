<?php

namespace App\Http\Requests\Fees;

use App\Rules\ValidName;
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

        return [
            'academic_year_id' => ['sometimes', 'required', Rule::exists('academic_years', 'id')],
            'grade_level_id' => ['nullable', Rule::exists('grade_levels', 'id')],
            'fee_category_id' => ['sometimes', 'required', Rule::exists('fee_categories', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:150', new ValidName],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'frequency' => ['sometimes', 'required', Rule::in(['one_time', 'monthly', 'quarterly', 'term', 'annual'])],
            'due_day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
