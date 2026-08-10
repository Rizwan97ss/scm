<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'username' => $this->username,
            'phone' => $this->phone,
            'gender' => $this->gender?->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status?->value,
            'must_change_password' => $this->must_change_password,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'designation' => $this->whenLoaded('designation', fn () => $this->designation ? ['id' => $this->designation->id, 'name' => $this->designation->name] : null),
            'employee_id' => $this->employee_id,
            'hire_date' => $this->hire_date?->toDateString(),
            'avatar_url' => $this->getFirstMediaUrl('avatar') ?: null,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            // Always included (not just on /me): the frontend caches whatever
            // UserResource shape login/me/update return, and a route-name-gated
            // field here silently produces a User with no permissions after
            // login specifically, breaking every permission-gated nav item.
            'permissions' => $this->getAllPermissions()->pluck('name'),
            // Lets the frontend poll /auth/me after a self-service signup's
            // Stripe Checkout redirect to detect when the webhook has synced
            // billing_status, without a dedicated endpoint. Null for Super
            // Admin and anywhere `school` isn't eager loaded (see
            // MeController/LoginController/SignupController).
            // TODO(tenancy): Super Admin detection needs PlatformUser (Sub-phase E) -- do not guess at a replacement.
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'plan_name' => $this->school->plan?->name,
                'billing_status' => $this->school->billing_status,
                'trial_ends_at' => $this->school->trial_ends_at?->toIso8601String(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
