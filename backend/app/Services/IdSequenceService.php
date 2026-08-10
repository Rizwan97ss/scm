<?php

namespace App\Services;

use App\Models\IdSequence;
use Illuminate\Support\Facades\DB;

/**
 * Race-safe monotonic counters (e.g. "admission_number:2026") scoped per school.
 * Used anywhere a sequential, gap-tolerant human-readable number is needed
 * (admission numbers now; invoice/receipt numbers in a later phase).
 */
class IdSequenceService
{
    public function next(int $schoolId, string $key): int
    {
        return DB::transaction(function () use ($schoolId, $key) {
            $sequence = IdSequence::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = IdSequence::query()->create([
                    'key' => $key,
                    'last_value' => 0,
                ]);

                $sequence = IdSequence::query()
                    ->where('id', $sequence->id)
                    ->lockForUpdate()
                    ->first();
            }

            $sequence->increment('last_value');

            return (int) $sequence->last_value;
        });
    }
}
