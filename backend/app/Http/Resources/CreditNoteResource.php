<?php

namespace App\Http\Resources;

use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditNote */
class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'credit_note_number' => $this->credit_note_number,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'issued_by' => $this->whenLoaded('issuedBy', fn () => ['id' => $this->issuedBy->id, 'full_name' => $this->issuedBy->full_name]),
            'issued_at' => $this->issued_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
