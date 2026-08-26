<?php

namespace App\Services;

use App\Models\School;
use Carbon\Carbon;

/**
 * Generates admission numbers from a school-configurable format string
 * (settings key `students.admission_number_format`, default "{YEAR}-{SEQ}")
 * with tokens {SCHOOL}, {YEAR}, {SEQ}, backed by a per-year IdSequence counter.
 */
class StudentIdGeneratorService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly IdSequenceService $idSequences,
    ) {}

    public function generate(School $school, ?Carbon $admissionDate = null): string
    {
        $year = ($admissionDate ?? now())->year;

        $format = (string) $this->settings->get('students.admission_number_format', '{YEAR}-{SEQ}', $school->id);
        $padding = (int) $this->settings->get('students.admission_number_padding', 4, $school->id);

        $sequence = $this->idSequences->next($school->id, "admission_number:{$year}");

        return strtr($format, [
            '{SCHOOL}' => $school->short_name,
            '{YEAR}' => (string) $year,
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }
}
