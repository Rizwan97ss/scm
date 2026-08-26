<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\FeeNumberService;

/**
 * Records money a school already physically collected (cash, cheque, bank
 * transfer, UPI, card swiped on a separate terminal) — no external API call,
 * no charge is initiated by this class. This is the default and only
 * gateway in Phase 8; see PaymentGatewayInterface's docblock for why a real
 * online-charging gateway is deliberately not built here yet.
 */
class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly FeeNumberService $feeNumbers) {}

    public function name(): string
    {
        return 'manual';
    }

    public function record(Invoice $invoice, array $data, User $receivedBy): Payment
    {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'payment_number' => $this->feeNumbers->nextPaymentNumber(tenant()),
            'amount' => $data['amount'],
            'method' => $data['method'],
            'gateway' => $this->name(),
            'reference_number' => $data['reference_number'] ?? null,
            'paid_at' => $data['paid_at'],
            'notes' => $data['notes'] ?? null,
            'received_by' => $receivedBy->id,
        ]);
    }
}
