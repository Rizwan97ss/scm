<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

/** Mirrors LeaveRequestPolicy's self-visibility shape — payroll.view/.manage only expand reach to *other* staff's payslips. */
class PayslipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payslip $payslip): bool
    {
        if ($payslip->user_id === $user->id) {
            return true;
        }

        return $user->can('payroll.view');
    }

    public function generate(User $user): bool
    {
        return $user->can('payroll.manage');
    }

    public function markPaid(User $user, Payslip $payslip): bool
    {
        return $user->can('payroll.manage');
    }
}
