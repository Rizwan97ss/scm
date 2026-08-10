<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * School records (campuses/tenants) are managed exclusively by Super Admin,
 * who bypasses this policy entirely via the Gate::before hook in
 * AppServiceProvider. Every other role is denied outright; a school's own
 * profile/branding is edited through the Settings module instead.
 */
class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, School $school): bool
    {
        return false;
    }

    /**
     * Always false — this governs the ADMIN-facing POST /schools endpoint
     * only. Self-service signup (SignupController) creates schools through
     * SchoolProvisioningService::provision() calling School::create()
     * directly, deliberately never through authorize('create', School::class)
     * — it's a separate, public, rate-limited entry point with its own
     * guard (a valid Stripe Checkout completion), not a Spatie permission.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, School $school): bool
    {
        return false;
    }

    public function delete(User $user, School $school): bool
    {
        return false;
    }
}
