<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'class_teacher_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')->where('school_id', $schoolId)],
        ];
    }
}
