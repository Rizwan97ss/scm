<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stands up one new tenant: the School row, its default roles/permissions
 * (the same matrix RolePermissionSeeder applies, sourced from here so
 * there's one copy of it), its first admin user, and plan-derived settings.
 * Callable synchronously from an HTTP controller (self-service signup) as
 * well as from the CLI seeder — both paths share this one code path.
 */
class SchoolProvisioningService
{
    private const SCHOOL_SCOPED_ROLE_PERMISSIONS = [
        'School Admin' => [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'settings.view', 'settings.edit',
            'audit-logs.view',
            'academic-years.view', 'academic-years.create', 'academic-years.edit', 'academic-years.delete',
            'academic-structure.view', 'academic-structure.create', 'academic-structure.edit', 'academic-structure.delete',
            'timetable.view', 'timetable.create', 'timetable.edit', 'timetable.delete',
            'students.view', 'students.create', 'students.edit', 'students.delete', 'students.import', 'students.export',
            'guardians.view', 'guardians.create', 'guardians.edit', 'guardians.delete',
            'enrollment.manage',
            'student-attendance.view', 'student-attendance.mark', 'student-attendance.edit', 'student-attendance.export',
            'staff-attendance.view', 'staff-attendance.mark', 'staff-attendance.edit', 'staff-attendance.export',
            'grading.view', 'grading.manage',
            'exams.view', 'exams.create', 'exams.edit', 'exams.delete', 'exams.publish',
            'exam-marks.view', 'exam-marks.enter', 'exam-marks.edit', 'exam-marks.export',
            'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
            'online-exams.view', 'online-exams.configure',
            'homework.view', 'homework.create', 'homework.edit', 'homework.delete', 'homework.grade',
            'remarks.view', 'remarks.create', 'remarks.edit', 'remarks.delete',
            'dashboard.view',
            'billing.view', 'billing.manage',
            'fees.view', 'fees.create', 'fees.edit', 'fees.delete',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.void',
            'invoices.record-payment', 'invoices.issue-credit-note', 'invoices.view-reports',
            'designations.view', 'designations.create', 'designations.edit', 'designations.delete',
            'leave.view', 'leave.manage',
            'payroll.view', 'payroll.manage',
            'library.view', 'library.manage',
            'transport.view', 'transport.manage',
            'hostel.view', 'hostel.manage',
            'front-desk.view', 'front-desk.manage',
            'certificates.view', 'certificates.create', 'certificates.edit', 'certificates.delete', 'certificates.issue',
            'notice-board.view', 'notice-board.create', 'notice-board.edit', 'notice-board.delete', 'notice-board.publish',
            'communication.view', 'communication.manage',
        ],
        'Principal' => [
            'users.view',
            'settings.view',
            'audit-logs.view',
            'academic-years.view',
            'academic-structure.view', 'academic-structure.create', 'academic-structure.edit', 'academic-structure.delete',
            'timetable.view', 'timetable.create', 'timetable.edit', 'timetable.delete',
            'students.view', 'students.export',
            'guardians.view',
            'enrollment.manage',
            'student-attendance.view', 'student-attendance.edit', 'student-attendance.export',
            'staff-attendance.view', 'staff-attendance.edit', 'staff-attendance.export',
            'grading.view', 'grading.manage',
            'exams.view', 'exams.create', 'exams.edit', 'exams.delete', 'exams.publish',
            'exam-marks.view', 'exam-marks.edit', 'exam-marks.export',
            'questions.view', 'questions.edit',
            'online-exams.view', 'online-exams.configure',
            'homework.view',
            'remarks.view',
            'dashboard.view',
            'fees.view',
            'invoices.view', 'invoices.view-reports',
            'designations.view',
            'leave.view',
            'library.view',
            'transport.view',
            'hostel.view',
            'front-desk.view',
            'certificates.view',
            'notice-board.view',
            'communication.view',
        ],
        'Management' => [
            'users.view',
            'settings.view',
            'audit-logs.view',
            'academic-years.view',
            'academic-structure.view',
            'timetable.view',
            'students.view', 'students.export',
            'guardians.view',
            'student-attendance.view', 'student-attendance.export',
            'staff-attendance.view', 'staff-attendance.export',
            'grading.view',
            'exams.view',
            'exam-marks.view', 'exam-marks.export',
            'questions.view',
            'online-exams.view',
            'homework.view',
            'remarks.view',
            'dashboard.view',
            'fees.view',
            'invoices.view', 'invoices.view-reports',
            'designations.view',
            'leave.view',
            'library.view',
            'transport.view',
            'hostel.view',
            'front-desk.view',
            'certificates.view',
            'notice-board.view',
            'communication.view',
        ],
        'Accountant' => [
            'academic-years.view',
            'academic-structure.view',
            'students.view',
            'guardians.view',
            'dashboard.view',
            'fees.view', 'fees.create', 'fees.edit', 'fees.delete',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.void',
            'invoices.record-payment', 'invoices.issue-credit-note', 'invoices.view-reports',
        ],
        'HR Staff' => [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view',
            'academic-years.view', 'academic-structure.view',
            'timetable.view',
            'students.view',
            'staff-attendance.view', 'staff-attendance.mark', 'staff-attendance.edit', 'staff-attendance.export',
            'dashboard.view',
            'designations.view', 'designations.create', 'designations.edit', 'designations.delete',
            'leave.view', 'leave.manage',
            'payroll.view', 'payroll.manage',
            'hostel.view', 'hostel.manage',
        ],
        'Receptionist' => [
            'academic-years.view', 'academic-structure.view',
            'timetable.view',
            'students.view', 'students.create',
            'guardians.view', 'guardians.create',
            'dashboard.view',
            'front-desk.view', 'front-desk.manage',
        ],
        'Teacher' => [
            'academic-years.view', 'academic-structure.view',
            'timetable.view',
            'students.view',
            'guardians.view',
            'student-attendance.view', 'student-attendance.mark', 'student-attendance.edit',
            'exams.view',
            'exam-marks.view', 'exam-marks.enter', 'exam-marks.edit',
            'questions.view', 'questions.create', 'questions.edit',
            'online-exams.view', 'online-exams.configure',
            'homework.view', 'homework.create', 'homework.edit', 'homework.delete', 'homework.grade',
            'remarks.view', 'remarks.create', 'remarks.edit', 'remarks.delete',
            'dashboard.view',
        ],
        'Class Teacher' => [
            'academic-years.view', 'academic-structure.view',
            'timetable.view',
            'students.view',
            'guardians.view',
            'student-attendance.view', 'student-attendance.mark', 'student-attendance.edit', 'student-attendance.export',
            'exams.view',
            'exam-marks.view', 'exam-marks.enter', 'exam-marks.edit', 'exam-marks.export',
            'questions.view', 'questions.create', 'questions.edit',
            'online-exams.view', 'online-exams.configure',
            'homework.view', 'homework.create', 'homework.edit', 'homework.delete', 'homework.grade',
            'remarks.view', 'remarks.create', 'remarks.edit', 'remarks.delete',
            'dashboard.view',
        ],
        'Student' => [
            'academic-years.view',
            'timetable.view',
            'students.view',
            'student-attendance.view',
            'exams.view',
            'exam-marks.view',
            'homework.view',
            'remarks.view',
            'dashboard.view',
            'invoices.view',
            'certificates.view',
        ],
        'Parent' => [
            'academic-years.view',
            'timetable.view',
            'students.view',
            'guardians.view',
            'student-attendance.view',
            'exams.view',
            'exam-marks.view',
            'homework.view',
            'remarks.view',
            'dashboard.view',
            'invoices.view',
            'certificates.view',
        ],
        'Librarian' => [
            'dashboard.view',
            'students.view',
            'library.view', 'library.manage',
        ],
        'Transport Staff' => [
            'dashboard.view',
            'students.view',
            'transport.view', 'transport.manage',
        ],
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Creates (or resyncs) the school's default roles and their default
     * permission sets. Idempotent — safe to re-run against a school that
     * already has these roles (e.g. from the CLI seeder).
     */
    public function seedDefaultRoles(School $school): void
    {
        $registrar = app(PermissionRegistrar::class);

        foreach (self::SCHOOL_SCOPED_ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Projects a plan's seat limits into per-school Settings (group
     * 'billing'), so the rest of the app can read them via the already
     * school/global-aware SettingsService rather than joining to `plans`.
     */
    public function applyPlanLimits(School $school, Plan $plan): void
    {
        $this->settings->set('billing.max_students', $plan->max_students, $school->id, SettingType::Integer, 'billing');
        $this->settings->set('billing.max_staff', $plan->max_staff, $school->id, SettingType::Integer, 'billing');
        $this->settings->set('billing.plan_key', $plan->key, $school->id, SettingType::String, 'billing');
    }

    /**
     * Stands up a brand-new tenant end to end: the School row, its roles,
     * its first admin user (assigned "School Admin"), and plan-derived
     * settings — all inside one transaction. Deliberately Stripe-agnostic:
     * callers handle Stripe customer/subscription creation afterward,
     * since that's an external HTTP call that shouldn't hold a DB
     * transaction open.
     *
     * @param  array<string, mixed>  $schoolAttributes
     * @param  array<string, mixed>  $adminAttributes
     *
     * TODO(tenancy): this whole method needs Sub-phase E's rewrite, not a
     * patch — School::create() no longer atomically implies a ready tenant
     * database (see School's $dispatchesEvents: creating the row triggers
     * async-capable provisioning via stancl's job pipeline), seedDefaultRoles()
     * and the admin User::create() below must run INSIDE that tenant's own
     * connection once it exists, and 'school_id' => $school->id is a
     * straightforwardly broken reference to a column User no longer has.
     * Left as a known-broken placeholder rather than patched piecemeal.
     */
    public function provision(array $schoolAttributes, array $adminAttributes, Plan $plan): School
    {
        return DB::transaction(function () use ($schoolAttributes, $adminAttributes, $plan) {
            $school = School::query()->create([...$schoolAttributes, 'plan_id' => $plan->id]);

            $this->seedDefaultRoles($school);

            $admin = User::query()->create([...$adminAttributes, 'school_id' => $school->id]);
            $admin->assignRole('School Admin');

            $this->applyPlanLimits($school, $plan);

            return $school;
        });
    }
}
