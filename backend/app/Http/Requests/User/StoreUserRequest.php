<?php

namespace App\Http\Requests\User;

use App\Rules\ValidName;
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

        return [
            'first_name' => ['required', 'string', 'max:100', new ValidName],
            'last_name' => ['required', 'string', 'max:100', new ValidName],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')],
            'hire_date' => ['nullable', 'date'],
        ];
    }
}
