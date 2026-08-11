<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Public, unauthenticated — this is the self-service tenant-signup
 * endpoint, not admin-gated user/school creation (see SchoolPolicy's
 * docblock for why SchoolProvisioningService never calls authorize() at
 * all). Rate limiting (throttle:5,60 on the route) is the abuse guard.
 */
class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school.name' => ['required', 'string', 'max:255'],
            'school.short_name' => ['required', 'string', 'max:50', Rule::unique('schools', 'short_name')],
            'school.email' => ['nullable', 'email', 'max:255'],
            'school.phone' => ['nullable', 'string', 'max:30'],
            'school.timezone' => ['sometimes', 'string', 'max:100'],
            'school.locale' => ['sometimes', 'string', 'max:10'],

            'admin.first_name' => ['required', 'string', 'max:100'],
            'admin.last_name' => ['required', 'string', 'max:100'],
            // No Rule::unique('users', ...) here -- there is no single `users`
            // table to check against (each tenant has its own, and this
            // request runs on the central domain with no tenant resolved at
            // all). Every signup provisions a brand-new, empty tenant, so
            // the new admin's email is trivially unique within it regardless.
            'admin.email' => ['required', 'string', 'email', 'max:255'],
            'admin.password' => ['required', 'confirmed', Password::defaults()],

            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ];
    }
}
