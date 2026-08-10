<?php

namespace App\Http\Resources;

use App\Models\SalaryStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalaryStructure */
class SalaryStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'full_name' => $this->user->full_name]),
            'basic_salary' => $this->basic_salary,
            'allowances' => $this->allowances,
            'deductions' => $this->deductions,
            'net_salary' => $this->net_salary,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
