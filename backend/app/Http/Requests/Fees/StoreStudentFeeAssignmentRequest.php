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
        $schoolId = $this->user()->school_id;

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'fee_structure_id' => ['required', Rule::exists('fee_structures', 'id')->where('school_id', $schoolId)],
            'discount_type' => ['required', Rule::in(['none', 'percentage', 'fixed'])],
            'discount_value' => ['required_unless:discount_type,none', 'nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
