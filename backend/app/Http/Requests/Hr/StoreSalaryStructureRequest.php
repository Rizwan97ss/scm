<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'basic_salary' => ['required', 'numeric', 'min:0.01'],
            'allowances' => ['sometimes', 'numeric', 'min:0'],
            'deductions' => ['sometimes', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
