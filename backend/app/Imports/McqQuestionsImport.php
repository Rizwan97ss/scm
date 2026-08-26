<?php

namespace App\Imports;

use App\Models\ExamSubject;
use App\Models\OnlineTestQuestion;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
 * Same shape as StudentsImport: format-level checks (required fields, valid
 * types) live in rules(), business-rule checks that can't be expressed as a
 * static rule (does correct_option actually point at a filled option? is
 * this question text a repeat of an earlier row in this same file?) happen
 * in onRow() as soft Failures — one bad row never aborts the rest of the
 * file.
 */
class McqQuestionsImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    private int $importedCount = 0;

    /** @var array<string, true> normalized (trimmed, lowercased) question text already seen in this file */
    private array $seenQuestions = [];

    /** Running sequence for auto-attaching into $examSubject, continuing after whatever it already has. */
    private int $nextSequence = 0;

    public function __construct(
        private readonly Subject $subject,
        private readonly User $performedBy,
        private readonly ?ExamSubject $examSubject = null,
    ) {
        if ($this->examSubject) {
            $this->nextSequence = ($this->examSubject->onlineTestQuestions()->max('sequence') ?? -1) + 1;
        }
    }

    public function onRow(Row $row): void
    {
        $data = $row->toCollection();
        $questionText = trim((string) $data['question']);
        $normalized = mb_strtolower($questionText);

        if (isset($this->seenQuestions[$normalized])) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'question',
                ['This question is a duplicate of an earlier row in this file.'],
                $data->toArray(),
            );

            return;
        }

        $options = [
            'a' => trim((string) ($data['option_a'] ?? '')),
            'b' => trim((string) ($data['option_b'] ?? '')),
            'c' => trim((string) ($data['option_c'] ?? '')),
            'd' => trim((string) ($data['option_d'] ?? '')),
        ];
        $filledOptions = array_filter($options, fn (string $value) => $value !== '');

        if (count($filledOptions) < 2) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'option_a',
                ['At least two options (option_a and option_b) are required.'],
                $data->toArray(),
            );

            return;
        }

        $correctKey = mb_strtolower(trim((string) $data['correct_option']));

        if (! isset($filledOptions[$correctKey])) {
            $filledLetters = strtoupper(implode(', ', array_keys($filledOptions)));
            $this->failures[] = new Failure(
                $row->getIndex(), 'correct_option',
                ["correct_option must reference one of this row's filled options ({$filledLetters})."],
                $data->toArray(),
            );

            return;
        }

        $this->seenQuestions[$normalized] = true;

        // Two filled, both literally "true"/"false" — the only case this
        // app's existing True/False question type applies to (see
        // Question.type's own docblock: both types grade identically via
        // question_options, this is purely a display distinction).
        $isTrueFalse = count($filledOptions) === 2
            && in_array(mb_strtolower($filledOptions['a']), ['true', 'false'], true)
            && in_array(mb_strtolower($filledOptions['b']), ['true', 'false'], true);

        DB::transaction(function () use ($data, $questionText, $filledOptions, $correctKey, $isTrueFalse) {
            $question = Question::query()->create([
                'subject_id' => $this->subject->id,
                'type' => $isTrueFalse ? 'true_false' : 'mcq',
                'text' => $questionText,
                'default_marks' => (float) $data['marks'],
                'negative_marks' => isset($data['negative_marks']) && $data['negative_marks'] !== '' ? (float) $data['negative_marks'] : null,
                'created_by' => $this->performedBy->id,
            ]);

            $sequence = 0;
            foreach ($filledOptions as $key => $text) {
                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'option_text' => $text,
                    'is_correct' => $key === $correctKey,
                    'sequence' => $sequence++,
                ]);
            }

            if ($this->examSubject) {
                OnlineTestQuestion::query()->create([
                    'exam_subject_id' => $this->examSubject->id,
                    'question_id' => $question->id,
                    'sequence' => $this->nextSequence++,
                ]);
            }
        });

        $this->importedCount++;
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * No 'string' type rule on question/option_* — a purely numeric-looking
     * cell (a very ordinary MCQ answer choice: "3", "4", "5", "6") comes
     * back from PhpSpreadsheet as an actual int/float, not a string, since
     * Excel doesn't preserve "this was typed as text" once a cell round-trips
     * through a writer/reader. onRow() already (string)-casts every one of
     * these fields before use, so validation only needs to confirm they're
     * present and a sane length, not that they arrived as a PHP string.
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'max:1000'],
            'option_a' => ['required', 'max:255'],
            'option_b' => ['required', 'max:255'],
            'option_c' => ['nullable', 'max:255'],
            'option_d' => ['nullable', 'max:255'],
            'correct_option' => ['required', 'in:a,b,c,d,A,B,C,D'],
            'marks' => ['required', 'numeric', 'min:0.01'],
            'negative_marks' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
