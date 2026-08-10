<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;

/**
 * Renders a CertificateTemplate's body against a specific student and
 * snapshots the result into a Certificate row — the rendered text never
 * changes later even if the template it came from is subsequently edited,
 * same "snapshot at generation time" reasoning as Payslip's amounts.
 */
class CertificateService
{
    public function __construct(
        private readonly IdSequenceService $idSequences,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{student_id: int, issued_date?: string}  $data
     */
    public function issue(CertificateTemplate $template, array $data, User $issuedBy): Certificate
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $issuedDate = isset($data['issued_date']) ? Carbon::parse($data['issued_date']) : now();

        return Certificate::query()->create([
            'school_id' => $template->school_id,
            'student_id' => $student->id,
            'certificate_template_id' => $template->id,
            'certificate_number' => $this->nextCertificateNumber($template->school),
            'issued_date' => $issuedDate,
            'issued_by' => $issuedBy->id,
            'content' => $this->render($template->body, $student, $issuedDate),
        ]);
    }

    private function render(string $body, Student $student, Carbon $issuedDate): string
    {
        return strtr($body, [
            '{{student_name}}' => $student->full_name,
            '{{admission_number}}' => $student->admission_number,
            '{{grade_level}}' => $student->currentGradeLevel?->name ?? '',
            '{{section}}' => $student->currentSection?->name ?? '',
            '{{school_name}}' => $student->school->name,
            '{{date}}' => $issuedDate->toFormattedDateString(),
        ]);
    }

    private function nextCertificateNumber(School $school): string
    {
        $year = Carbon::now()->year;
        $format = (string) $this->settings->get('certificates.certificate_number_format', 'CERT-{YEAR}-{SEQ}', $school->id);
        $padding = (int) $this->settings->get('certificates.number_padding', 4, $school->id);
        $sequence = $this->idSequences->next($school->id, "certificate_number:{$year}");

        return strtr($format, [
            '{SCHOOL}' => $school->short_name,
            '{YEAR}' => (string) $year,
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }
}
