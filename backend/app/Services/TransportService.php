<?php

namespace App\Services;

use App\Models\StudentTransportAssignment;

/**
 * Only one active transport assignment per student — same "close, don't
 * delete" shape as SalaryStructure (Phase 9), since a historical assignment
 * may still be worth keeping around for record-keeping.
 */
class TransportService
{
    public function assign(array $data): StudentTransportAssignment
    {
        StudentTransportAssignment::query()
            ->where('student_id', $data['student_id'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return StudentTransportAssignment::query()->create([...$data, 'is_active' => true]);
    }
}
