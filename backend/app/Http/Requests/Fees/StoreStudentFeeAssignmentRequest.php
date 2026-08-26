<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentFeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'student_id' => ['required', Rule::exists('students', 'id')],
            'fee_structure_id' => ['required', Rule::exists('fee_structures', 'id')],
            'discount_type' => ['required', Rule::in(['none', 'percentage', 'fixed'])],
            'discount_value' => ['required_unless:discount_type,none', 'nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
