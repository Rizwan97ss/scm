<?php

namespace App\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Models\School;

/**
 * Enforces the seat limits SchoolProvisioningService::applyPlanLimits()
 * projected into billing.max_students/billing.max_staff — read via
 * SettingsService, the same global/per-school-override mechanism every
 * other configurable behavior in the app already goes through, not a
 * second "read a limit" code path.
 *
 * Deliberately narrow scope: only these two concrete, unambiguous,
 * revenue-relevant seat limits are enforced. Per-module feature-flag
 * gating (locking whole modules like Exams behind a tier) is data-modeled
 * on Plan but not live-enforced yet — see docs/roadmap.md's Phase 6 notes.
 *
 * Callers are responsible for skipping this entirely when the acting user
 * is Super Admin (school_id === null) — plan limits are a business rule
 * a platform operator can always override, not a hard invariant.
 */
class PlanLimitService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function assertCanAddStudent(School $school): void
    {
        $max = $this->settings->get('billing.max_students', null, $school->id);

        if ($max !== null && $school->studentCount() >= $max) {
            throw new PlanLimitExceededException(
                "You've reached your plan's student limit ({$max}). Upgrade your plan to add more students."
            );
        }
    }

    public function assertCanAddStaffUser(School $school): void
    {
        $max = $this->settings->get('billing.max_staff', null, $school->id);

        if ($max !== null && $school->staffCount() >= $max) {
            throw new PlanLimitExceededException(
                "You've reached your plan's staff limit ({$max}). Upgrade your plan to add more staff."
            );
        }
    }
}
