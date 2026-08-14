<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\AssessmentComponentType;
use App\Models\ExamType;
use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

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
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage-mfa',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'settings.view', 'settings.edit',
            'audit-logs.view',
            'data-export.school',
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
            'exam-marks.view', 'exam-marks.enter', 'exam-marks.edit', 'exam-marks.export', 'exam-marks.publish', 'exam-marks.import',
            'questions.view', 'questions.create', 'questions.edit', 'questions.delete', 'questions.import',
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
            'exam-marks.view', 'exam-marks.edit', 'exam-marks.export', 'exam-marks.publish',
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
            'exam-marks.view', 'exam-marks.enter', 'exam-marks.edit', 'exam-marks.import',
            'questions.view', 'questions.create', 'questions.edit', 'questions.import',
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
            'exam-marks.view', 'exam-marks.enter', 'exam-marks.edit', 'exam-marks.export', 'exam-marks.publish', 'exam-marks.import',
            'questions.view', 'questions.create', 'questions.edit', 'questions.import',
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
     *
     * Under database-per-tenant, Spatie's own `permissions` table lives in
     * THIS tenant's database too, not a shared one — a brand-new tenant has
     * zero rows in it, and syncPermissions() throws PermissionDoesNotExist
     * for a name that isn't there yet. PermissionSeeder used to run once,
     * globally, for the whole (single, shared) app; now it has to run once
     * per tenant, which is what actually makes this method self-sufficient
     * regardless of caller (matches its own "idempotent" contract above)
     * rather than relying on some other seeder having already run first
     * against this exact tenant connection.
     */
    public function seedDefaultRoles(School $school): void
    {
        app(PermissionSeeder::class)->run();

        $registrar = app(PermissionRegistrar::class);

        foreach (self::SCHOOL_SCOPED_ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Seeds the canonical exam types (Class Test, Unit Test, Monthly Test,
     * Trimester, Semester, Mid-Term, Final/Annual) and assessment component
     * types (Online MCQ, Written, Practical, Oral/Viva) every tenant starts
     * with — both are admin-editable/extendable afterward (see
     * ExamTypeController/AssessmentComponentTypeController), this just
     * seeds a sensible starting set. Idempotent (firstOrCreate by code), so
     * re-running it (e.g. via the exam-config:rollout command against an
     * already-live tenant) never duplicates rows.
     */
    public function seedDefaultExamConfig(School $school): void
    {
        $examTypes = [
            ['code' => 'class_test', 'name' => 'Class Test', 'sequence' => 1],
            ['code' => 'unit_test', 'name' => 'Unit Test', 'sequence' => 2],
            ['code' => 'monthly_test', 'name' => 'Monthly Test', 'sequence' => 3],
            ['code' => 'trimester', 'name' => 'Trimester', 'sequence' => 4],
            ['code' => 'semester', 'name' => 'Semester', 'sequence' => 5],
            ['code' => 'mid_term', 'name' => 'Mid-Term', 'sequence' => 6],
            ['code' => 'final_annual', 'name' => 'Final / Annual Examination', 'sequence' => 7],
        ];
        foreach ($examTypes as $type) {
            ExamType::query()->firstOrCreate(['code' => $type['code']], $type);
        }

        $componentTypes = [
            ['code' => 'online_mcq', 'name' => 'Online MCQ', 'is_auto_graded' => true, 'sequence' => 1],
            ['code' => 'written', 'name' => 'Written', 'is_auto_graded' => false, 'sequence' => 2],
            ['code' => 'practical', 'name' => 'Practical', 'is_auto_graded' => false, 'sequence' => 3],
            ['code' => 'oral_viva', 'name' => 'Oral / Viva', 'is_auto_graded' => false, 'sequence' => 4],
        ];
        foreach ($componentTypes as $type) {
            AssessmentComponentType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
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
     * Stands up a brand-new tenant end to end: the School row (landlord),
     * its physical tenant database (synchronous — see School's
     * $dispatchesEvents docblock; no queue worker exists in this app), and
     * — inside that tenant's own connection — its default roles, first
     * admin user ("School Admin"), plan-derived settings, and a one-time
     * login token for the signup-completion handoff (see SignupController:
     * a session created while this request is on the central/signup domain
     * can't carry over to the tenant's own subdomain, since session cookies
     * don't cross origins — this token is what proves "this really is the
     * admin who just signed up" on that first request to the new subdomain).
     *
     * No DB::transaction wrapper: the landlord write and the tenant writes
     * are on entirely different physical connections, so one transaction
     * can never span both. On any failure after the School row exists, the
     * whole tenant (row + physical database, whatever got created before
     * the failure) is torn down instead — a half-provisioned tenant with no
     * working admin login is worse than no tenant at all.
     *
     * @param  array<string, mixed>  $schoolAttributes
     * @param  array<string, mixed>  $adminAttributes
     * @return array{school: School, admin: User, login_token: string}
     */
    public function provision(array $schoolAttributes, array $adminAttributes, Plan $plan): array
    {
        $school = School::query()->create([...$schoolAttributes, 'plan_id' => $plan->id]);

        try {
            $result = $school->run(function () use ($adminAttributes, $plan, $school) {
                $this->seedDefaultRoles($school);
                $this->seedDefaultExamConfig($school);

                $admin = User::query()->create($adminAttributes);
                $admin->assignRole('School Admin');

                $this->applyPlanLimits($school, $plan);

                return ['admin' => $admin, 'login_token' => Password::broker()->createToken($admin)];
            });
        } catch (Throwable $e) {
            $this->teardown($school);

            throw $e;
        }

        return ['school' => $school, 'admin' => $result['admin'], 'login_token' => $result['login_token']];
    }

    /**
     * Physically drops a tenant's database and hard-deletes its landlord
     * row — irreversible. Originally only the provisioning-failure rollback
     * path above; Phase 15's whole-school offboarding
     * (`PlatformSchoolController::offboard()`, `mode=delete`) reuses this
     * exact sequence rather than duplicating it.
     */
    public function teardown(School $school): void
    {
        // End tenancy (disconnects the connection) before deleting the
        // database: for the SQLite manager that's a real file, and Windows
        // won't let you delete one that's still open.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $school->database()->manager()->deleteDatabase($school);
        $school->forceDelete();
    }
}
