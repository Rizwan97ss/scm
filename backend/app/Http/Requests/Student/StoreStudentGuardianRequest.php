<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'guardian_id' => ['nullable', Rule::exists('guardians', 'id')->where('school_id', $schoolId)],
            'first_name' => ['required_without:guardian_id', 'string', 'max:100'],
            'last_name' => ['required_without:guardian_id', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required_without:guardian_id', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'relationship_type' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
        ];
    }
}
