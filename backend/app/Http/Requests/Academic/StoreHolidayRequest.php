<?php

namespace App\Http\Requests\Academic;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', Rule::exists('academic_years', 'id')],
            'name' => ['required', 'string', 'max:100', new ValidName],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['sometimes', Rule::in(['public', 'school_specific'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
