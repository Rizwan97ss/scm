<?php

namespace App\Imports;

use App\Models\ExamSubject;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Same shape as McqQuestionsImport/StudentsImport: format-level checks in
 * rules(), business-rule checks that can't be static rules (does this
 * admission_number actually resolve to a student in this section? is the
 * mark within this component's own max_marks?) happen in onRow() as soft
 * Failures — one bad row never aborts the rest of the file.
 *
 * Deliberately does NOT write to ExamMark itself — onRow() only validates
 * and collects entries(); the controller hands the whole batch to
 * ExamService::markBulk() once, reusing its existing upsert-by-
 * (exam_subject_id, student_id) semantics instead of duplicating them here.
 */
class ExamMarksImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    /** @var array<int, array{student_id: int, marks_obtained: ?float, is_absent: bool, remarks: ?string}> */
    private array $entries = [];

    /** @var array<string, true> normalized (trimmed, lowercased) admission numbers already seen in this file */
    private array $seenAdmissionNumbers = [];

    public function __construct(private readonly ExamSubject $examSubject) {}

    public function onRow(Row $row): void
    {
        $data = $row->toCollection();
        $admissionNumber = trim((string) $data['admission_number']);
        $normalized = mb_strtolower($admissionNumber);

        if (isset($this->seenAdmissionNumbers[$normalized])) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'admission_number',
                ['This admission number is a duplicate of an earlier row in this file.'],
                $data->toArray(),
            );

            return;
        }

        $student = Student::query()
            ->where('admission_number', $admissionNumber)
            ->where('current_section_id', $this->examSubject->section_id)
            ->first();

        if (! $student) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'admission_number',
                ["No student with admission number \"{$admissionNumber}\" was found in this component's section."],
                $data->toArray(),
            );

            return;
        }

        $this->seenAdmissionNumbers[$normalized] = true;

        $isAbsent = in_array(mb_strtolower(trim((string) ($data['is_absent'] ?? ''))), ['1', 'true', 'yes', 'y'], true);
        $rawMarks = trim((string) ($data['marks_obtained'] ?? ''));
        $marksObtained = ($isAbsent || $rawMarks === '') ? null : (float) $rawMarks;

        if ($marksObtained !== null && $marksObtained > $this->examSubject->max_marks) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'marks_obtained',
                ["Cannot exceed the maximum of {$this->examSubject->max_marks}."],
                $data->toArray(),
            );

            return;
        }

        $this->entries[] = [
            'student_id' => $student->id,
            'marks_obtained' => $marksObtained,
            'is_absent' => $isAbsent,
            'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
        ];
    }

    /** @return array<int, array{student_id: int, marks_obtained: ?float, is_absent: bool, remarks: ?string}> */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * No 'string' rule on admission_number — a purely numeric-looking one
     * (some schools use plain numbers) comes back from PhpSpreadsheet as an
     * actual int, not a string, same gotcha already fixed once in
     * McqQuestionsImport. onRow() already (string)-casts before use.
     */
    public function rules(): array
    {
        return [
            'admission_number' => ['required', 'max:50'],
            'marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'is_absent' => ['nullable'],
            'remarks' => ['nullable', 'max:500'],
        ];
    }
}
