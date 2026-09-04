<?php

namespace App\Models;

use App\Enums\CourseMaterialType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['school_id', 'section_id', 'subject_id', 'teacher_id', 'title', 'description', 'type', 'url', 'is_published'])]
class CourseMaterial extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => CourseMaterialType::class,
            'is_published' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(CourseMaterialProgress::class);
    }

    /** Same shape as Homework::scopeVisibleTo() — see that model's docblock. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            $sectionId = Student::query()->where('user_id', $user->id)->value('current_section_id');

            return $query->when($sectionId, fn ($q) => $q->where('section_id', $sectionId), fn ($q) => $q->whereRaw('1 = 0'))
                ->where('is_published', true);
        }

        if ($user->hasRole('Parent')) {
            $sectionIds = Student::query()
                ->whereHas('guardians', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('current_section_id');

            return $query->whereIn('section_id', $sectionIds)->where('is_published', true);
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
                ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
                ->unique();

            return $query->whereIn('section_id', $sectionIds);
        }

        return $query;
    }

    /** Mirrors Homework::isTaughtBy() — same subject/section teaching-assignment rule. */
    public function isTaughtBy(User $user): bool
    {
        return ClassSubjectTeacher::query()
            ->where('section_id', $this->section_id)
            ->where('subject_id', $this->subject_id)
            ->where('teacher_id', $user->id)
            ->exists();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'type', 'is_published'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
