<?php

namespace App\Support;

use App\Models\ClassSubjectTeacher;
use App\Models\Department;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\Homework;
use App\Models\ImportLog;
use App\Models\Question;
use App\Models\Room;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Reverses an import by soft-deleting exactly the records it created (never
 * a record it only updated — see SimpleLookupImport::createdModels() /
 * ImportLogItem). Deliberately scoped to the five lookup-table entities
 * (Departments, Grade Levels, Sections, Subjects, Rooms): these have small,
 * well-understood dependent graphs with no per-row business logic of their
 * own, unlike Students (enrollment history + guardians attach in the same
 * transaction as the create, so "any dependent" would false-positive on
 * every row) or Staff/Guardians (account deletion / shared-link semantics
 * need their own design). Undo for those is intentionally not built here.
 *
 * Every affected model uses SoftDeletes, so undo is itself reversible —
 * "undo" is a soft delete, restorable the same way any other delete in this
 * app is, not a permanent purge.
 */
class ImportUndoService
{
    /** @var array<class-string, \Closure(Model): bool> a record is blocked from undo if this returns true — i.e. something outside the import now depends on it. */
    private const DEPENDENT_CHECKS = [
        Department::class => [self::class, 'departmentHasDependents'],
        GradeLevel::class => [self::class, 'gradeLevelHasDependents'],
        Room::class => [self::class, 'roomHasDependents'],
        Subject::class => [self::class, 'subjectHasDependents'],
        Section::class => [self::class, 'sectionHasDependents'],
    ];

    /**
     * @return array{deleted: int, blocked: array<int, array{type: string, id: int, label: string}>}
     */
    public function undo(ImportLog $importLog): array
    {
        if ($importLog->dry_run) {
            throw ValidationException::withMessages(['import_log' => 'This was only a preview — nothing was written, so there is nothing to undo.']);
        }

        if ($importLog->undone_at) {
            throw ValidationException::withMessages(['import_log' => 'This import has already been undone.']);
        }

        $items = $importLog->items()->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['import_log' => 'Undo is not available for this import.']);
        }

        $deleted = 0;
        $blocked = [];

        foreach ($items as $item) {
            $modelClass = $item->model_type;

            if (! array_key_exists($modelClass, self::DEPENDENT_CHECKS)) {
                throw ValidationException::withMessages(['import_log' => 'Undo is not available for this import.']);
            }

            $model = $modelClass::find($item->model_id);

            if (! $model) {
                $deleted++; // already gone (e.g. deleted separately since) — nothing left to undo for this row.

                continue;
            }

            $check = self::DEPENDENT_CHECKS[$modelClass];

            if ($check($model)) {
                $blocked[] = [
                    'type' => class_basename($modelClass),
                    'id' => $model->getKey(),
                    'label' => (string) ($model->name ?? $model->getKey()),
                ];

                continue;
            }

            $model->delete();
            $deleted++;
        }

        $importLog->update(['undone_at' => now()]);

        return ['deleted' => $deleted, 'blocked' => $blocked];
    }

    private static function departmentHasDependents(Department $department): bool
    {
        return $department->subjects()->exists();
    }

    private static function gradeLevelHasDependents(GradeLevel $gradeLevel): bool
    {
        return $gradeLevel->sections()->exists();
    }

    private static function roomHasDependents(Room $room): bool
    {
        return Section::query()->where('room_id', $room->id)->exists();
    }

    private static function subjectHasDependents(Subject $subject): bool
    {
        return ClassSubjectTeacher::query()->where('subject_id', $subject->id)->exists()
            || ExamSubject::query()->where('subject_id', $subject->id)->exists()
            || Homework::query()->where('subject_id', $subject->id)->exists()
            || TimetableEntry::query()->where('subject_id', $subject->id)->exists()
            || Question::query()->where('subject_id', $subject->id)->exists();
    }

    private static function sectionHasDependents(Section $section): bool
    {
        return $section->students()->exists()
            || $section->classSubjectTeachers()->exists()
            || $section->timetableEntries()->exists();
    }
}
