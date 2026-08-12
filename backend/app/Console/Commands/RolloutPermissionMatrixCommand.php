<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SchoolProvisioningService;
use Illuminate\Console\Command;

/**
 * SchoolProvisioningService::SCHOOL_SCOPED_ROLE_PERMISSIONS changes (new
 * permissions, new role grants) only apply to schools provisioned AFTER
 * the change — already-seeded roles in existing tenants aren't
 * retroactively updated (documented in docs/deployment.md § 10 since the
 * tenancy conversion). Run this once after any deploy that changes the
 * matrix — e.g. Phase 15's new users.manage-mfa/data-export.school
 * permissions — so already-live schools pick them up without a fresh
 * provision. Safe to re-run: seedDefaultRoles() is idempotent
 * (Role::findOrCreate() + syncPermissions()).
 */
class RolloutPermissionMatrixCommand extends Command
{
    protected $signature = 'permissions:rollout {--school= : Only roll out to the school with this slug}';

    protected $description = 'Re-sync SchoolProvisioningService\'s default role/permission matrix into every existing tenant';

    public function handle(SchoolProvisioningService $provisioning): int
    {
        $slug = $this->option('school');

        $schools = School::query()->when($slug, fn ($q) => $q->where('slug', $slug))->get();

        if ($schools->isEmpty()) {
            $this->error('No matching school found.');

            return self::FAILURE;
        }

        $schools->each(function (School $school) use ($provisioning) {
            $school->run(fn () => $provisioning->seedDefaultRoles($school));
            $this->line("  {$school->name}: role/permission matrix synced");
        });

        return self::SUCCESS;
    }
}
