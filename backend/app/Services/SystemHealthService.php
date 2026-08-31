<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\GradingScale;
use App\Models\Section;
use App\Models\Subject;

/**
 * Read-only setup/configuration diagnostics for the Settings > System Health
 * page — surfaces the same gotchas docs/school-setup-guide.md describes
 * ("a dropdown with no options usually means an earlier phase is missing")
 * as an actual in-app checklist instead of something an admin only
 * discovers by hitting a dead end. Every check here is cheap (single count
 * queries), so this is safe to call on every page load, not just on demand.
 */
class SystemHealthService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function checks(): array
    {
        $checks = [
            $this->currentAcademicYear(),
            $this->terms(),
            $this->sections(),
            $this->classTeachers(),
            $this->subjects(),
            $this->defaultGradingScale(),
            $this->branding(),
            $this->debugMode(),
        ];

        $total = count($checks);
        $ok = count(array_filter($checks, fn ($c) => $c['status'] === 'ok'));

        return [
            'checks' => $checks,
            'completion_percentage' => $total > 0 ? (int) round($ok / $total * 100) : 100,
        ];
    }

    private function currentAcademicYear(): array
    {
        $exists = AcademicYear::query()->where('is_current', true)->exists();

        return $this->check(
            key: 'academic_year',
            label: 'Current academic year',
            status: $exists ? 'ok' : 'error',
            message: $exists ? 'A current academic year is set.' : 'No academic year is marked current — students, sections, exams, and fee structures all key off this.',
        );
    }

    private function terms(): array
    {
        $currentYear = AcademicYear::query()->where('is_current', true)->first();

        if (! $currentYear) {
            return $this->check('terms', 'Terms', 'error', 'No current academic year to attach terms to yet.');
        }

        $count = $currentYear->terms()->count();

        return $this->check(
            key: 'terms',
            label: 'Terms',
            status: $count > 0 ? 'ok' : 'warning',
            message: $count > 0 ? "{$count} term(s) configured for the current year." : 'No terms configured for the current academic year — exams and report cards are scoped to a term.',
        );
    }

    private function sections(): array
    {
        $count = Section::query()->count();

        return $this->check(
            key: 'sections',
            label: 'Sections',
            status: $count > 0 ? 'ok' : 'error',
            message: $count > 0 ? "{$count} section(s) exist." : 'No sections exist yet — you cannot admit a student until at least one section exists.',
        );
    }

    private function classTeachers(): array
    {
        $missing = Section::query()->whereNull('class_teacher_id')->count();
        $total = Section::query()->count();

        if ($total === 0) {
            return $this->check('class_teachers', 'Class teachers', 'warning', 'No sections exist yet.');
        }

        return $this->check(
            key: 'class_teachers',
            label: 'Class teachers',
            status: $missing === 0 ? 'ok' : 'warning',
            message: $missing === 0 ? 'Every section has a class teacher assigned.' : "{$missing} of {$total} section(s) have no class teacher assigned.",
        );
    }

    private function subjects(): array
    {
        $count = Subject::query()->count();

        return $this->check(
            key: 'subjects',
            label: 'Subjects',
            status: $count > 0 ? 'ok' : 'warning',
            message: $count > 0 ? "{$count} subject(s) configured." : 'No subjects configured yet.',
        );
    }

    private function defaultGradingScale(): array
    {
        $exists = GradingScale::query()->where('is_default', true)->exists();

        return $this->check(
            key: 'grading_scale',
            label: 'Default grading scale',
            status: $exists ? 'ok' : 'warning',
            message: $exists ? 'A default grading scale is set.' : 'No grading scale is marked default — an exam subject without its own scale has nothing to fall back to.',
        );
    }

    private function branding(): array
    {
        $schoolName = trim((string) $this->settings->get('school.name', ''));

        return $this->check(
            key: 'branding',
            label: 'School identity',
            status: $schoolName !== '' ? 'ok' : 'warning',
            message: $schoolName !== '' ? 'School name is configured.' : 'School name is empty — it shows up on the login screen and every PDF (report cards, invoices, ID cards).',
        );
    }

    private function debugMode(): array
    {
        $isProblem = app()->isProduction() && config('app.debug') === true;

        return $this->check(
            key: 'debug_mode',
            label: 'Debug mode',
            status: $isProblem ? 'error' : 'ok',
            message: $isProblem
                ? 'APP_DEBUG is on in production — this leaks stack traces (including data in them) on any server error. Turn it off before real use.'
                : 'Debug mode is off.',
        );
    }

    private function check(string $key, string $label, string $status, string $message): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'message' => $message];
    }
}
