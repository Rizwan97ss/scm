<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeeCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;
        $feeCategory = $this->route('fee_category');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('fee_categories', 'name')->where('school_id', $schoolId)->ignore($feeCategory)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
