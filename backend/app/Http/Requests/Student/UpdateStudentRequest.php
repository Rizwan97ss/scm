<?php

namespace App\Http\Requests\Student;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100', new ValidName],
            'last_name' => ['sometimes', 'required', 'string', 'max:100', new ValidName],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'roll_number' => ['nullable', 'string', 'max:50'],
            'previous_school_name' => ['nullable', 'string', 'max:255'],
            'previous_school_details' => ['nullable', 'string'],
            'medical_info' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150', new ValidName],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }
}
