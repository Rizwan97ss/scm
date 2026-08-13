<?php

namespace App\Http\Requests\Fees;

use App\Rules\ValidName;
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
        $feeCategory = $this->route('fee_category');

        return [
            'name' => ['required', 'string', 'max:100', new ValidName, Rule::unique('fee_categories', 'name')->ignore($feeCategory)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
