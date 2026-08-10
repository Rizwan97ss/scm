<?php

namespace App\Models;

use App\Enums\HostelAllocationStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'student_id', 'hostel_room_id', 'bed_number', 'allocated_date', 'vacated_date', 'status'])]
class HostelAllocation extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'allocated_date' => 'date',
            'vacated_date' => 'date',
            'status' => HostelAllocationStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hostelRoom(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class);
    }
}
