<?php

namespace App\Models;

use App\Enums\RemarkCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'student_id', 'author_id', 'section_id', 'category', 'body', 'visible_to_guardian'])]
class StudentRemark extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => RemarkCategory::class,
            'visible_to_guardian' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Student sees remarks about themselves regardless of the guardian-visibility
     * flag (that flag only controls what a Parent sees). Parent only sees
     * remarks explicitly marked visible_to_guardian. Teacher/Class Teacher see
     * remarks for students in a section they teach or lead, not just their own
     * authored remarks — a Class Teacher reviewing a student needs the full
     * picture from every teacher, not a fragmented per-author view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            $studentId = Student::query()->where('user_id', $user->id)->value('id');

            return $query->where('student_id', $studentId);
        }

        if ($user->hasRole('Parent')) {
            $studentIds = Student::query()
                ->whereHas('guardians', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('id');

            return $query->where('visible_to_guardian', true)->whereIn('student_id', $studentIds);
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
                ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
                ->unique();

            return $query->whereHas('student', fn ($q) => $q->whereIn('current_section_id', $sectionIds));
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['category', 'body', 'visible_to_guardian'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
