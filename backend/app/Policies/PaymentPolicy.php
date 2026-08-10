<?php

namespace App\Policies;

/**
 * A Payment has no create/update/delete of its own — it's only ever
 * produced via Invoice::recordPayment (see InvoicePolicy) and never edited
 * or deleted (immutable ledger entry; a mistake is corrected with a credit
 * note, not a rewrite). This policy exists solely so PaymentController's
 * index/view listing can gate on the same invoices.view permission.
 */
class PaymentPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'invoices';
}
