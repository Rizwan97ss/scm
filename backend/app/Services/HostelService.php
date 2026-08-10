<?php

namespace App\Services;

use App\Enums\HostelAllocationStatus;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use Illuminate\Support\Facades\DB;

/**
 * Owns room-capacity enforcement and the "one active allocation per
 * student" rule — same "close, don't delete" shape as
 * StudentTransportAssignment/SalaryStructure.
 */
class HostelService
{
    /**
     * @param  array{student_id: int, hostel_room_id: int, bed_number?: ?string, allocated_date: string}  $data
     */
    public function allocate(array $data): HostelAllocation
    {
        return DB::transaction(function () use ($data) {
            $room = HostelRoom::query()->findOrFail($data['hostel_room_id']);

            abort_if($room->occupiedCount() >= $room->capacity, 422, 'This room is already at full capacity.');

            HostelAllocation::query()
                ->where('student_id', $data['student_id'])
                ->where('status', HostelAllocationStatus::Allocated)
                ->update(['status' => HostelAllocationStatus::Vacated, 'vacated_date' => $data['allocated_date']]);

            return HostelAllocation::query()->create([
                'student_id' => $data['student_id'],
                'hostel_room_id' => $room->id,
                'bed_number' => $data['bed_number'] ?? null,
                'allocated_date' => $data['allocated_date'],
                'status' => HostelAllocationStatus::Allocated,
            ]);
        });
    }

    public function vacate(HostelAllocation $allocation): HostelAllocation
    {
        abort_if($allocation->status === HostelAllocationStatus::Vacated, 422, 'This allocation has already been vacated.');

        $allocation->update(['status' => HostelAllocationStatus::Vacated, 'vacated_date' => now()->toDateString()]);

        return $allocation->refresh();
    }
}
