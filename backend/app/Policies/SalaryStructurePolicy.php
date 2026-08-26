<?php

namespace App\Policies;

use App\Models\SalaryStructure;
use App\Models\User;

/**
 * Salary structures are HR/Admin-only end to end, unlike LeaveRequest/Payslip
 * — there's no "view your own structure" self-service surface (a staff
 * member sees the *result* via their payslips, not the structure itself).
 */
class SalaryStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view') || $user->can('payroll.manage');
    }

    public function view(User $user, SalaryStructure $salaryStructure): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.manage');
    }

    public function update(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('payroll.manage');
    }

    public function delete(User $user, SalaryStructure $salaryStructure): bool
    {
        return $this->update($user, $salaryStructure);
    }
}
