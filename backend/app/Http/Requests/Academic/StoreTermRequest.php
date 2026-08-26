<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'name' => ['required', 'string', 'max:50', new ValidName, Rule::unique('terms', 'name')->where('academic_year_id', $this->input('academic_year_id'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
