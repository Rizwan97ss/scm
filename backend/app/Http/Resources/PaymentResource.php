<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'full_name' => $this->student->full_name]),
            'payment_number' => $this->payment_number,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'gateway' => $this->gateway,
            'reference_number' => $this->reference_number,
            'paid_at' => $this->paid_at?->toDateString(),
            'notes' => $this->notes,
            'received_by' => $this->whenLoaded('receivedBy', fn () => ['id' => $this->receivedBy->id, 'full_name' => $this->receivedBy->full_name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
