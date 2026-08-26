<?php

namespace App\Http\Requests\Homework;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'section_id' => ['required', Rule::exists('sections', 'id')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            // Not ValidName — a homework title is a free-text headline, not an identifier.
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
