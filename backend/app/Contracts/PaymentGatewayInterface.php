<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

/**
 * Seam between "an invoice got money against it" and how that money was
 * actually collected. `ManualPaymentGateway` (cash/cheque/bank-transfer/UPI/
 * card recorded by a staff member after the fact) is the only implementation
 * shipped in Phase 8 — a real online gateway (Stripe, Razorpay, PayU, ...)
 * is a deliberate future integration, not built here (see roadmap.md's
 * cross-cutting decisions: the first concrete non-manual implementation is
 * a "pause and confirm with the user" checkpoint, not something to guess at).
 * Swapping in a real gateway later is a container-binding change in
 * AppServiceProvider, not a rewrite of anything that calls this interface.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * @param  array{amount: float, method: string, reference_number?: ?string, paid_at: string, notes?: ?string}  $data
     */
    public function record(Invoice $invoice, array $data, User $receivedBy): Payment;
}
