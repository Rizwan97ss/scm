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
        $schoolId = $this->user()->school_id;

        return [
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'section_id' => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
