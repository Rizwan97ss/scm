<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'student_id' => ['required', Rule::exists('students', 'id')],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_category_id' => ['required', Rule::exists('fee_categories', 'id')],
            'items.*.fee_structure_id' => ['nullable', Rule::exists('fee_structures', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
