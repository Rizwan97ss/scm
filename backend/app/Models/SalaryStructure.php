<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'user_id', 'basic_salary', 'allowances', 'deductions', 'effective_from', 'effective_to', 'is_active'])]
class SalaryStructure extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'basic_salary' => 'float',
            'allowances' => 'float',
            'deductions' => 'float',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function netSalary(): Attribute
    {
        return Attribute::get(fn () => round($this->basic_salary + $this->allowances - $this->deductions, 2));
    }
}
