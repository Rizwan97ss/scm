<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'invoice_id', 'fee_structure_id', 'fee_category_id', 'description', 'quantity', 'unit_amount', 'amount'])]
class InvoiceItem extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'float',
            'amount' => 'float',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }
}
