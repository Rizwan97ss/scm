<?php

namespace App\Imports;

use App\Imports\Concerns\SimpleLookupImport;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\Section;
use App\Rules\ValidName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Sections don't have a single unique code column like the other lookup
 * imports — their real uniqueness (matching the DB constraint on the
 * `sections` table) is (academic_year_id, grade_level_id, name), so this
 * overrides uniqueKey() to build a composite key instead of relying on
 * SimpleLookupImport's single-column default. class_teacher_id is
 * deliberately not importable here — docs/school-setup-guide.md's own
 * Phase 2 treats "assign a class teacher" as a separate step after
 * sections exist, not part of creating them.
 */
class SectionsImport extends SimpleLookupImport
{
    public function __construct(
        private readonly AcademicYear $academicYear,
        bool $dryRun = false,
        string $mode = self::MODE_CREATE,
    ) {
        parent::__construct($dryRun, $mode);
    }

    protected function uniqueKeyField(): string
    {
        return 'name';
    }

    protected function uniqueKey(Collection $data): string
    {
        $gradeLevelCode = mb_strtolower(trim((string) $data['grade_level_code']));
        $name = mb_strtolower(trim((string) $data['name']));

        return "{$gradeLevelCode}|{$name}";
    }

    protected function modelClass(): string
    {
        return Section::class;
    }

    protected function mapRow(Collection $data, int $rowIndex): ?array
    {
        $gradeLevelCode = trim((string) $data['grade_level_code']);
        $gradeLevel = GradeLevel::query()->where('code', $gradeLevelCode)->first();

        if (! $gradeLevel) {
            $this->failures[] = new Failure(
                $rowIndex, 'grade_level_code',
                ["No grade level with code \"{$gradeLevelCode}\" was found."],
                $data->toArray(),
            );

            return null;
        }

        $roomId = null;
        $roomCode = trim((string) ($data['room_code'] ?? ''));

        if ($roomCode !== '') {
            $room = Room::query()->where('code', $roomCode)->first();

            if (! $room) {
                $this->failures[] = new Failure(
                    $rowIndex, 'room_code',
                    ["No room with code \"{$roomCode}\" was found."],
                    $data->toArray(),
                );

                return null;
            }

            $roomId = $room->id;
        }

        // Mirrors StoreSectionRequest's scoped Rule::unique — can't express
        // that as a static rules() rule here since grade_level_id isn't
        // known until the code above resolves it. Only a 'create'-mode
        // concern: update/upsert modes are supposed to find this same
        // existing row (via findExisting() below) and update it, not fail.
        if ($this->mode === self::MODE_CREATE) {
            $exists = Section::query()
                ->where('academic_year_id', $this->academicYear->id)
                ->where('grade_level_id', $gradeLevel->id)
                ->where('name', $data['name'])
                ->exists();

            if ($exists) {
                $this->failures[] = new Failure(
                    $rowIndex, 'name',
                    ['A section with this name already exists for this grade level in the current academic year.'],
                    $data->toArray(),
                );

                return null;
            }
        }

        return [
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => $data['name'],
            'capacity' => isset($data['capacity']) && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
            'room_id' => $roomId,
        ];
    }

    public function rules(): array
    {
        return [
            'grade_level_code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:20', new ValidName],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'room_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Matches on $attributes' already-resolved academic_year_id/grade_level_id, not the row's raw grade_level_code — the composite-key equivalent of the base class's single-column default. */
    protected function findExisting(Collection $data, array $attributes): ?Model
    {
        return Section::query()
            ->where('academic_year_id', $attributes['academic_year_id'])
            ->where('grade_level_id', $attributes['grade_level_id'])
            ->where('name', $attributes['name'])
            ->first();
    }
}
