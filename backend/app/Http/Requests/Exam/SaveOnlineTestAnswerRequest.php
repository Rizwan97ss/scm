<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOnlineTestAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'question_id' => ['required', Rule::exists('questions', 'id')->where('school_id', $schoolId)],
            'selected_option_id' => ['nullable', Rule::exists('question_options', 'id')->where('school_id', $schoolId)],
        ];
    }
}
