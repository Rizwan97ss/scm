<?php

namespace App\Http\Requests\Student;

use App\Rules\ValidName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'first_name' => ['required', 'string', 'max:100', new ValidName],
            'last_name' => ['required', 'string', 'max:100', new ValidName],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'current_grade_level_id' => ['nullable', Rule::exists('grade_levels', 'id')],
            'current_section_id' => ['nullable', Rule::exists('sections', 'id')],
            'current_department_id' => ['nullable', Rule::exists('departments', 'id')],
            'roll_number' => ['nullable', 'string', 'max:50'],
            'admission_date' => ['required', 'date'],
            'previous_school_name' => ['nullable', 'string', 'max:255'],
            'previous_school_details' => ['nullable', 'string'],
            'medical_info' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],

            'guardians' => ['sometimes', 'array'],
            'guardians.*.guardian_id' => ['nullable', Rule::exists('guardians', 'id')],
            'guardians.*.first_name' => ['required_without:guardians.*.guardian_id', 'string', 'max:100'],
            'guardians.*.last_name' => ['required_without:guardians.*.guardian_id', 'string', 'max:100'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.phone' => ['required_without:guardians.*.guardian_id', 'string', 'max:30'],
            'guardians.*.relationship_type' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'guardians.*.is_primary' => ['sometimes', 'boolean'],
            'guardians.*.can_pickup' => ['sometimes', 'boolean'],
        ];
    }
}
