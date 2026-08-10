<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'user_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class LeaveRequest extends Model
{
    use BelongsToSchool, HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'status' => LeaveStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Every staff member sees their own requests regardless of leave.view —
     * that permission only expands visibility to *other* staff's requests,
     * mirroring StaffAttendancePolicy's self-visibility rule exactly.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('leave.view')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'days'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
