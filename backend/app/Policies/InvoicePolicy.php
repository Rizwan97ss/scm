<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'invoices';

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.void');
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.record-payment');
    }

    public function issueCreditNote(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.issue-credit-note');
    }

    public function viewReports(User $user): bool
    {
        return $user->can('invoices.view-reports');
    }
}
