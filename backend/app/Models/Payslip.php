<?php

namespace App\Models;

use App\Enums\PayslipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'user_id', 'salary_structure_id', 'payslip_number', 'month', 'year', 'basic_salary', 'allowances', 'deductions', 'net_salary', 'status', 'paid_at', 'generated_by', 'notes'])]
class Payslip extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'basic_salary' => 'float',
            'allowances' => 'float',
            'deductions' => 'float',
            'net_salary' => 'float',
            'status' => PayslipStatus::class,
            'paid_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** Every staff member sees their own payslips regardless of payroll.view — same shape as LeaveRequest::scopeVisibleTo(). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('payroll.view')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'net_salary'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
