<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'admission_number' => $this->student->admission_number,
            ]),
            'academic_year_id' => $this->academic_year_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_overdue' => $this->is_overdue,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'credit_total' => $this->credit_total,
            'balance' => $this->balance,
            'notes' => $this->notes,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'credit_notes' => CreditNoteResource::collection($this->whenLoaded('creditNotes')),
            'created_by' => $this->whenLoaded('createdBy', fn () => ['id' => $this->createdBy->id, 'full_name' => $this->createdBy->full_name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
