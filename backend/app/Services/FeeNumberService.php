<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Carbon;

/**
 * Generates invoice/receipt/credit-note numbers via IdSequenceService,
 * the same race-safe per-school counter mechanism StudentIdGeneratorService
 * uses for admission numbers — its own docblock names this exact use case.
 * Format strings are DB-driven settings (group 'fees') so a school can
 * rebrand its numbering without a code change, same pattern as
 * students.admission_number_format.
 */
class FeeNumberService
{
    public function __construct(
        private readonly IdSequenceService $idSequences,
        private readonly SettingsService $settings,
    ) {}

    public function nextInvoiceNumber(School $school): string
    {
        return $this->generate($school, 'invoice_number', 'fees.invoice_number_format', 'INV-{YEAR}-{SEQ}');
    }

    public function nextPaymentNumber(School $school): string
    {
        return $this->generate($school, 'payment_number', 'fees.receipt_number_format', 'RCT-{YEAR}-{SEQ}');
    }

    public function nextCreditNoteNumber(School $school): string
    {
        return $this->generate($school, 'credit_note_number', 'fees.credit_note_number_format', 'CN-{YEAR}-{SEQ}');
    }

    private function generate(School $school, string $sequenceKey, string $formatSettingKey, string $defaultFormat): string
    {
        $year = Carbon::now()->year;
        $format = (string) $this->settings->get($formatSettingKey, $defaultFormat, $school->id);
        $padding = (int) $this->settings->get('fees.number_padding', 4, $school->id);
        $sequence = $this->idSequences->next($school->id, "{$sequenceKey}:{$year}");

        return strtr($format, [
            '{SCHOOL}' => $school->short_name,
            '{YEAR}' => (string) $year,
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }
}
