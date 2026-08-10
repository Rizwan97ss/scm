<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('school_id', $schoolId)],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->where('school_id', $schoolId)],
            'hire_date' => ['nullable', 'date'],
        ];
    }
}
