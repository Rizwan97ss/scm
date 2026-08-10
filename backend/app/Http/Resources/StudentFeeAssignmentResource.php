<?php

namespace App\Http\Resources;

use App\Models\StudentFeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentFeeAssignment */
class StudentFeeAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'full_name' => $this->student->full_name]),
            'fee_structure' => $this->whenLoaded('feeStructure', fn () => ['id' => $this->feeStructure->id, 'name' => $this->feeStructure->name, 'amount' => $this->feeStructure->amount]),
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'reason' => $this->reason,
            'effective_amount' => $this->whenLoaded('feeStructure', fn () => $this->applyDiscount($this->feeStructure->amount)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
