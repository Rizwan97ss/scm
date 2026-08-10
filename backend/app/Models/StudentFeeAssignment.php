<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'student_id', 'fee_structure_id', 'discount_type', 'discount_value', 'reason'])]
class StudentFeeAssignment extends Model
{
    use BelongsToSchool, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    /**
     * Applies this assignment's discount to a base amount. A percentage
     * discount is capped at 100% of the base so a misconfigured >100%
     * value can never produce a negative invoice line.
     */
    public function applyDiscount(float $baseAmount): float
    {
        return match ($this->discount_type) {
            DiscountType::Percentage => round($baseAmount * (1 - min($this->discount_value, 100) / 100), 2),
            DiscountType::Fixed => max(0, round($baseAmount - $this->discount_value, 2)),
            default => $baseAmount,
        };
    }
}
