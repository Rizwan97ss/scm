<?php

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fee_category' => $this->whenLoaded('feeCategory', fn () => ['id' => $this->feeCategory->id, 'name' => $this->feeCategory->name]),
            'fee_structure_id' => $this->fee_structure_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'amount' => $this->amount,
        ];
    }
}
