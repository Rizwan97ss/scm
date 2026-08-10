<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'basic_salary' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'allowances' => ['sometimes', 'numeric', 'min:0'],
            'deductions' => ['sometimes', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'required', 'date'],
        ];
    }
}
