<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AppNotificationController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\PlatformLoginController;
use App\Http\Controllers\Api\V1\Auth\PlatformLogoutController;
use App\Http\Controllers\Api\V1\Auth\PlatformMeController;
use App\Http\Controllers\Api\V1\Auth\PlatformMfaController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SignupCompleteController;
use App\Http\Controllers\Api\V1\Auth\SignupController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BookIssueController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CertificateTemplateController;
use App\Http\Controllers\Api\V1\ClassSubjectTeacherController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DataExportController;
use App\Http\Controllers\Api\V1\AssessmentComponentTypeController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DesignationController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\ExamMarkController;
use App\Http\Controllers\Api\V1\ExamMarkImportController;
use App\Http\Controllers\Api\V1\ExamTypeController;
use App\Http\Controllers\Api\V1\QuestionImportController;
use App\Http\Controllers\Api\V1\FeeCategoryController;
use App\Http\Controllers\Api\V1\FeeReportController;
use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\GradeLevelController;
use App\Http\Controllers\Api\V1\GradingScaleController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\HomeworkController;
use App\Http\Controllers\Api\V1\HomeworkSubmissionController;
use App\Http\Controllers\Api\V1\HostelAllocationController;
use App\Http\Controllers\Api\V1\HostelController;
use App\Http\Controllers\Api\V1\HostelRoomController;
use App\Http\Controllers\Api\V1\IdCardController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\NoticeController;
use App\Http\Controllers\Api\V1\OnlineTestController;
use App\Http\Controllers\Api\V1\ParentPortalController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\Platform\PlatformMetricsController;
use App\Http\Controllers\Api\V1\Platform\PlatformSchoolController;
use App\Http\Controllers\Api\V1\Platform\SchoolPlanController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RouteController as TransportRouteController;
use App\Http\Controllers\Api\V1\SalaryStructureController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\StaffAttendanceController;
use App\Http\Controllers\Api\V1\StudentAttendanceController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentDocumentController;
use App\Http\Controllers\Api\V1\StudentEnrollmentController;
use App\Http\Controllers\Api\V1\StudentExportController;
use App\Http\Controllers\Api\V1\StudentFeeAssignmentController;
use App\Http\Controllers\Api\V1\StudentImportController;
use App\Http\Controllers\Api\V1\StudentRemarkController;
use App\Http\Controllers\Api\V1\StudentTransportAssignmentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TermController;
use App\Http\Controllers\Api\V1\TermResultController;
use App\Http\Controllers\Api\V1\TimetableController;
use App\Http\Controllers\Api\V1\TimetableEntryController;
use App\Http\Controllers\Api\V1\TimetablePeriodController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VisitorController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // =====================================================================
    // CENTRAL zone — no tenant resolved. Runs on the platform/signup domain
    // (localtest.me, no subdomain) — tenancy.subdomain middleware never
    // touches anything in this block. A school doesn't exist yet when
    // signup runs, and Super Admin isn't inside any one tenant at all.
    // =====================================================================

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('signup', SignupController::class)->name('signup')->middleware('throttle:5,60');
        Route::post('platform-login', PlatformLoginController::class)->name('platform-login')->middleware('throttle:10,1');
        Route::post('platform-mfa/verify-challenge', [PlatformMfaController::class, 'verifyChallenge'])
            ->name('platform-mfa.verify-challenge')->middleware('throttle:10,1');

        Route::middleware('auth:platform')->group(function () {
            Route::post('platform-logout', PlatformLogoutController::class)->name('platform-logout');
            Route::get('platform-me', PlatformMeController::class)->name('platform-me');
            Route::post('platform-mfa/setup', [PlatformMfaController::class, 'setup'])->name('platform-mfa.setup');
            Route::post('platform-mfa/confirm', [PlatformMfaController::class, 'confirm'])->name('platform-mfa.confirm');
            Route::post('platform-mfa/recovery-codes/regenerate', [PlatformMfaController::class, 'regenerateRecoveryCodes'])
                ->name('platform-mfa.recovery-codes.regenerate');
        });
    });

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

    Route::middleware('auth:platform')->group(function () {
        Route::apiResource('schools', SchoolController::class)->names('schools');

        Route::prefix('platform')->name('platform.')->group(function () {
            Route::get('schools', [PlatformSchoolController::class, 'index'])->name('schools.index');
            Route::get('schools/{school}', [PlatformSchoolController::class, 'show'])->name('schools.show');
            Route::post('schools/{school}/plan', [SchoolPlanController::class, 'update'])->name('schools.plan');
            Route::post('schools/{school}/offboard', [PlatformSchoolController::class, 'offboard'])->name('schools.offboard');
            Route::get('metrics', [PlatformMetricsController::class, 'index'])->name('metrics');
        });
    });

    // =====================================================================
    // TENANT zone — subdomain-resolved. tenancy.subdomain (see
    // bootstrap/app.php) runs before anything else below, switching the
    // active database connection to the school identified by the request's
    // Host header before any of these routes execute.
    // =====================================================================

    Route::middleware('tenancy.subdomain')->group(function () {

        // ---- Auth -------------------------------------------------------
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('login', LoginController::class)->name('login')->middleware('throttle:10,1');
            Route::post('mfa/verify-challenge', [MfaController::class, 'verifyChallenge'])
                ->name('mfa.verify-challenge')->middleware('throttle:10,1');
            // The other half of SignupController's cross-domain handoff —
            // see SignupCompleteController's own docblock.
            Route::post('signup/complete', SignupCompleteController::class)->name('signup.complete')->middleware('throttle:10,1');
            Route::post('forgot-password', ForgotPasswordController::class)->name('forgot-password')->middleware('throttle:5,1');
            Route::post('reset-password', ResetPasswordController::class)->name('reset-password')->middleware('throttle:5,1');

            Route::middleware('auth:sanctum')->group(function () {
                Route::post('logout', LogoutController::class)->name('logout');
                Route::get('me', MeController::class)->name('me');
                Route::put('password', PasswordController::class)->name('password');
                Route::post('email/verification-notification', [EmailVerificationController::class, 'notify'])
                    ->name('verification.send')
                    ->middleware('throttle:6,1');
                Route::post('mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
                Route::post('mfa/confirm', [MfaController::class, 'confirm'])->name('mfa.confirm');
                Route::post('mfa/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes'])
                    ->name('mfa.recovery-codes.regenerate');
            });

            Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                ->middleware(['auth:sanctum', 'signed'])
                ->name('verification.verify');
        });

        // ---- Public within this tenant (no auth, but tenant-resolved) -----
        Route::get('settings/public', [SettingController::class, 'public'])->name('settings.public');

        // ---- Everything below requires an authenticated tenant session ---
        // (EnsureSessionPasswordIsCurrent, invalidating a session once its
        // password goes stale, is registered globally in bootstrap/app.php
        // alongside EnsureUserIsActive — see that middleware's own docblock
        // for why: this app has two separate auth:sanctum route groups, and
        // platform routes need the same protection too.)
        Route::middleware('auth:sanctum')->group(function () {

            Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

            // Every uploaded file (student photos/documents, homework
            // attachments, avatars, ...) is served through here — see
            // MediaController's own docblock for why one generic,
            // authenticated route replaces the old direct public-disk URLs.
            Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');

            // Pusher channel-authorization endpoint (routes/channels.php),
            // registered manually rather than via withRouting()'s `channels:`
            // option — see AppServiceProvider::boot()'s comment. Living here
            // (tenant zone, auth:sanctum) rather than Laravel's default
            // unprefixed /broadcasting/auth is what makes Echo's private-
            // channel subscription actually resolve the correct tenant User,
            // the same way every other authenticated endpoint does.
            Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');

            // ---- Billing (school-scoped — a School Admin's own plan/trial) --
            Route::prefix('billing')->name('billing.')->group(function () {
                Route::get('/', [BillingController::class, 'show'])->name('show');
                Route::get('portal', [BillingController::class, 'portal'])->name('portal');
            });

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles');
            Route::post('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('users/{user}/mfa/reset', [UserController::class, 'resetMfa'])->name('users.mfa.reset');

            Route::apiResource('roles', RoleController::class)->names('roles');
            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

            Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

            // ---- Data export (Phase 15) -------------------------------------
            Route::post('account/data-export', [DataExportController::class, 'storeSelf'])->name('account.data-export.store');
            Route::get('account/data-export', [DataExportController::class, 'indexSelf'])->name('account.data-export.index');
            // ---- Self-service account deletion (Phase 15) --------------------
            Route::delete('account', [AccountController::class, 'destroy'])->name('account.destroy');
            Route::post('data-exports', [DataExportController::class, 'storeSchool'])->name('data-exports.store');
            Route::get('data-exports', [DataExportController::class, 'indexSchool'])->name('data-exports.index');
            Route::get('data-exports/{export}/download', [DataExportController::class, 'download'])->name('data-exports.download');

            // ---- Academic structure ----------------------------------------
            Route::apiResource('academic-years', AcademicYearController::class)->names('academic-years');
            Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');

            Route::apiResource('terms', TermController::class)->names('terms');
            Route::apiResource('departments', DepartmentController::class)->names('departments');
            Route::apiResource('grade-levels', GradeLevelController::class)->names('grade-levels');
            Route::apiResource('sections', SectionController::class)->names('sections');
            Route::apiResource('subjects', SubjectController::class)->names('subjects');
            Route::apiResource('rooms', RoomController::class)->names('rooms');
            Route::apiResource('holidays', HolidayController::class)->names('holidays');

            Route::get('class-subject-teachers', [ClassSubjectTeacherController::class, 'index'])->name('class-subject-teachers.index');
            Route::post('class-subject-teachers', [ClassSubjectTeacherController::class, 'store'])->name('class-subject-teachers.store');
            Route::delete('class-subject-teachers/{classSubjectTeacher}', [ClassSubjectTeacherController::class, 'destroy'])->name('class-subject-teachers.destroy');

            Route::apiResource('timetable-periods', TimetablePeriodController::class)->names('timetable-periods');

            Route::get('timetable', [TimetableController::class, 'index'])->name('timetable.index');
            Route::post('timetable-entries', [TimetableEntryController::class, 'store'])->name('timetable-entries.store');
            Route::put('timetable-entries/{timetableEntry}', [TimetableEntryController::class, 'update'])->name('timetable-entries.update');
            Route::delete('timetable-entries/{timetableEntry}', [TimetableEntryController::class, 'destroy'])->name('timetable-entries.destroy');

            // ---- Students & guardians ---------------------------------------
            Route::get('students', [StudentController::class, 'index'])->name('students.index');
            Route::post('students', [StudentController::class, 'store'])->name('students.store');
            Route::get('students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
            Route::post('students/import', StudentImportController::class)->name('students.import');
            Route::get('students/export', StudentExportController::class)->name('students.export');
            Route::post('students/bulk/promote', [StudentEnrollmentController::class, 'bulkPromote'])->name('students.bulk-promote');
            Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
            Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
            Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

            Route::get('students/{student}/documents', [StudentDocumentController::class, 'index'])->name('students.documents.index');
            Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');
            Route::delete('students/{student}/documents/{media}', [StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');

            Route::post('students/{student}/guardians', [StudentController::class, 'attachGuardian'])->name('students.guardians.store');
            Route::put('students/{student}/guardians/{guardian}', [StudentController::class, 'updateGuardianLink'])->name('students.guardians.update');
            Route::delete('students/{student}/guardians/{guardian}', [StudentController::class, 'detachGuardian'])->name('students.guardians.destroy');

            Route::get('students/{student}/enrollment-history', [StudentEnrollmentController::class, 'history'])->name('students.enrollment-history');
            Route::post('students/{student}/promote', [StudentEnrollmentController::class, 'promote'])->name('students.promote');
            Route::post('students/{student}/transfer', [StudentEnrollmentController::class, 'transfer'])->name('students.transfer');
            Route::post('students/{student}/withdraw', [StudentEnrollmentController::class, 'withdraw'])->name('students.withdraw');
            Route::post('students/{student}/graduate', [StudentEnrollmentController::class, 'graduate'])->name('students.graduate');
            Route::post('students/{student}/reactivate', [StudentEnrollmentController::class, 'reactivate'])->name('students.reactivate');
            Route::post('students/{student}/invite-portal-user', [StudentController::class, 'invitePortalUser'])->name('students.invite-portal-user');

            Route::get('guardians', [GuardianController::class, 'index'])->name('guardians.index');
            Route::get('guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
            Route::post('guardians/{guardian}/invite', [GuardianController::class, 'invite'])->name('guardians.invite');

            // ---- Attendance ----------------------------------------------------
            Route::get('attendance/students', [StudentAttendanceController::class, 'index'])->name('attendance.students.index');
            Route::post('attendance/students', [StudentAttendanceController::class, 'store'])->name('attendance.students.store');
            Route::get('attendance/students/summary', [StudentAttendanceController::class, 'summary'])->name('attendance.students.summary');
            Route::get('attendance/students/section-summary', [StudentAttendanceController::class, 'sectionSummary'])->name('attendance.students.section-summary');
            Route::put('attendance/students/{studentAttendance}', [StudentAttendanceController::class, 'update'])->name('attendance.students.update');

            Route::get('attendance/staff', [StaffAttendanceController::class, 'index'])->name('attendance.staff.index');
            Route::post('attendance/staff', [StaffAttendanceController::class, 'store'])->name('attendance.staff.store');
            Route::get('attendance/staff/summary', [StaffAttendanceController::class, 'summary'])->name('attendance.staff.summary');
            Route::post('attendance/staff/check-in', [StaffAttendanceController::class, 'checkIn'])->name('attendance.staff.check-in');
            Route::post('attendance/staff/check-out', [StaffAttendanceController::class, 'checkOut'])->name('attendance.staff.check-out');
            Route::put('attendance/staff/{staffAttendance}', [StaffAttendanceController::class, 'update'])->name('attendance.staff.update');

            // ---- Grading & Examinations -----------------------------------------
            Route::pattern('exam', '[0-9]+');
            Route::apiResource('grading-scales', GradingScaleController::class)->names('grading-scales');
            Route::apiResource('exam-types', ExamTypeController::class)->names('exam-types');
            Route::apiResource('assessment-component-types', AssessmentComponentTypeController::class)->names('assessment-component-types');

            Route::apiResource('exams', ExamController::class)->names('exams');
            Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
            Route::post('exams/{exam}/unpublish', [ExamController::class, 'unpublish'])->name('exams.unpublish');
            Route::get('exams/{exam}/report-card', [ExamController::class, 'reportCard'])->name('exams.report-card');
            Route::get('exams/{exam}/report-card/pdf', [ExamController::class, 'reportCardPdf'])->name('exams.report-card.pdf');
            Route::post('exams/{exam}/exam-subject-groups/{group}/publish', [ExamController::class, 'publishGroup'])->name('exams.exam-subject-groups.publish');
            Route::post('exams/{exam}/exam-subject-groups/{group}/unpublish', [ExamController::class, 'unpublishGroup'])->name('exams.exam-subject-groups.unpublish');
            Route::get('exams/{exam}/exam-subject-groups/{group}/result', [ExamController::class, 'groupResult'])->name('exams.exam-subject-groups.result');
            Route::delete('exams/{exam}/exam-subject-groups/{group}/components/{examSubject}', [ExamController::class, 'destroyComponent'])->name('exams.exam-subject-groups.components.destroy');

            Route::get('exam-subjects/{examSubject}/marks', [ExamMarkController::class, 'index'])->name('exam-subjects.marks.index');
            Route::post('exam-subjects/{examSubject}/marks', [ExamMarkController::class, 'store'])->name('exam-subjects.marks.store');
            Route::put('exam-marks/{examMark}', [ExamMarkController::class, 'update'])->name('exam-marks.update');
            Route::get('exam-subjects/{examSubject}/marks/import/template', [ExamMarkImportController::class, 'template'])->name('exam-subjects.marks.import.template');
            Route::post('exam-subjects/{examSubject}/marks/import', ExamMarkImportController::class)->name('exam-subjects.marks.import');

            // ---- Question bank & online examinations ----------------------------
            Route::apiResource('questions', QuestionController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('questions');
            Route::get('questions/import/template', [QuestionImportController::class, 'template'])->name('questions.import.template');
            Route::post('questions/import', QuestionImportController::class)->name('questions.import');

            Route::get('online-tests/mine', [OnlineTestController::class, 'myTests'])->name('online-tests.mine');
            Route::get('exam-subjects/{examSubject}/online-test-questions', [OnlineTestController::class, 'questions'])->name('exam-subjects.online-test-questions.index');
            Route::post('exam-subjects/{examSubject}/online-test-questions', [OnlineTestController::class, 'syncQuestions'])->name('exam-subjects.online-test-questions.store');
            Route::get('exam-subjects/{examSubject}/online-status', [OnlineTestController::class, 'onlineStatus'])->name('exam-subjects.online-status');
            Route::post('exam-subjects/{examSubject}/attempts', [OnlineTestController::class, 'start'])->name('exam-subjects.attempts.start')->middleware('throttle:10,1');
            Route::put('online-test-attempts/{attempt}/answers', [OnlineTestController::class, 'saveAnswer'])->name('online-test-attempts.answers.save');
            Route::post('online-test-attempts/{attempt}/submit', [OnlineTestController::class, 'submit'])->name('online-test-attempts.submit');
            Route::post('online-test-attempts/{attempt}/violations', [OnlineTestController::class, 'reportViolation'])->name('online-test-attempts.violations.store')->middleware('throttle:20,1');
            Route::get('online-test-attempts/{attempt}', [OnlineTestController::class, 'show'])->name('online-test-attempts.show');

            Route::get('terms/{term}/result', [TermResultController::class, 'show'])->name('terms.result');
            Route::get('terms/{term}/result/pdf', [TermResultController::class, 'pdf'])->name('terms.result.pdf');

            // ---- Teacher module: homework & remarks ------------------------------
            Route::apiResource('homework', HomeworkController::class)->names('homework');
            Route::post('homework/{homework}/attachments', [HomeworkController::class, 'storeAttachment'])->name('homework.attachments.store');
            Route::delete('homework/{homework}/attachments/{media}', [HomeworkController::class, 'destroyAttachment'])->name('homework.attachments.destroy');
            Route::get('homework/{homework}/submissions', [HomeworkSubmissionController::class, 'index'])->name('homework.submissions.index');
            Route::post('homework/{homework}/submit', [HomeworkSubmissionController::class, 'submit'])->name('homework.submit');
            Route::put('homework-submissions/{submission}/grade', [HomeworkSubmissionController::class, 'grade'])->name('homework-submissions.grade');

            Route::apiResource('student-remarks', StudentRemarkController::class)->names('student-remarks');

            // ---- Fees / Billing / Accounting (Phase 8) -----------------------
            // School-to-parent billing — distinct from Phase 6's platform-to-school
            // Stripe subscription billing under the 'billing' prefix above.
            Route::apiResource('fee-categories', FeeCategoryController::class)->names('fee-categories');

            Route::apiResource('fee-structures', FeeStructureController::class)->names('fee-structures');
            Route::post('fee-structures/{feeStructure}/generate-invoices', [FeeStructureController::class, 'generateInvoices'])->name('fee-structures.generate-invoices');

            Route::apiResource('student-fee-assignments', StudentFeeAssignmentController::class)->names('student-fee-assignments');

            Route::apiResource('invoices', InvoiceController::class)->names('invoices');
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
            Route::post('invoices/{invoice}/credit-notes', [InvoiceController::class, 'issueCreditNote'])->name('invoices.credit-notes.store');
            Route::get('students/{student}/fee-statement', [InvoiceController::class, 'statement'])->name('students.fee-statement');

            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
            Route::get('payments/{payment}/receipt/pdf', [PaymentController::class, 'receiptPdf'])->name('payments.receipt.pdf');

            Route::get('fee-reports/collection-summary', [FeeReportController::class, 'collectionSummary'])->name('fee-reports.collection-summary');
            Route::get('fee-reports/outstanding-dues', [FeeReportController::class, 'outstandingDues'])->name('fee-reports.outstanding-dues');

            // ---- Staff / HR (Phase 9) -----------------------------------------
            Route::apiResource('designations', DesignationController::class)->names('designations');

            Route::apiResource('leave-types', LeaveTypeController::class)->names('leave-types');

            Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
            Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
            Route::get('leave-requests/{id}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
            Route::post('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
            Route::post('leave-requests/{leaveRequest}/review', [LeaveRequestController::class, 'review'])->name('leave-requests.review');

            Route::apiResource('salary-structures', SalaryStructureController::class)->names('salary-structures');

            Route::get('payslips', [PayslipController::class, 'index'])->name('payslips.index');
            Route::post('payslips/generate', [PayslipController::class, 'generate'])->name('payslips.generate');
            Route::post('payslips/{id}/mark-paid', [PayslipController::class, 'markPaid'])->name('payslips.mark-paid');
            Route::get('payslips/{payslip}/receipt', [PayslipController::class, 'receipt'])->name('payslips.receipt');
            Route::get('payslips/{payslip}/receipt/pdf', [PayslipController::class, 'receiptPdf'])->name('payslips.receipt.pdf');

            // ---- Library (Phase 10) --------------------------------------------
            Route::apiResource('books', BookController::class)->names('books');
            Route::post('books/{book}/issue', [BookIssueController::class, 'store'])->name('books.issue');
            Route::get('book-issues', [BookIssueController::class, 'index'])->name('book-issues.index');
            Route::post('book-issues/{id}/return', [BookIssueController::class, 'returnBook'])->name('book-issues.return');

            // ---- Transport (Phase 10) -------------------------------------------
            Route::apiResource('vehicles', VehicleController::class)->names('vehicles');
            Route::apiResource('routes', TransportRouteController::class)->names('routes');
            Route::post('routes/{route}/stops', [TransportRouteController::class, 'storeStop'])->name('routes.stops.store');
            Route::delete('routes/{route}/stops/{stop}', [TransportRouteController::class, 'destroyStop'])->name('routes.stops.destroy');
            Route::get('student-transport-assignments', [StudentTransportAssignmentController::class, 'index'])->name('student-transport-assignments.index');
            Route::post('student-transport-assignments', [StudentTransportAssignmentController::class, 'store'])->name('student-transport-assignments.store');

            // ---- Hostel (Phase 10) ------------------------------------------------
            Route::apiResource('hostels', HostelController::class)->names('hostels');
            Route::apiResource('hostel-rooms', HostelRoomController::class)->names('hostel-rooms');
            Route::get('hostel-allocations', [HostelAllocationController::class, 'index'])->name('hostel-allocations.index');
            Route::post('hostel-allocations', [HostelAllocationController::class, 'store'])->name('hostel-allocations.store');
            Route::post('hostel-allocations/{id}/vacate', [HostelAllocationController::class, 'vacate'])->name('hostel-allocations.vacate');

            // ---- Front Desk / Visitor Management (Phase 10) ------------------------
            Route::get('visitors', [VisitorController::class, 'index'])->name('visitors.index');
            Route::post('visitors', [VisitorController::class, 'store'])->name('visitors.store');
            Route::post('visitors/{id}/check-out', [VisitorController::class, 'checkOut'])->name('visitors.check-out');

            // ---- Certificates & ID Cards (Phase 11) ------------------------------
            Route::apiResource('certificate-templates', CertificateTemplateController::class)->names('certificate-templates');
            Route::post('certificate-templates/{certificate_template}/issue', [CertificateController::class, 'store'])->name('certificate-templates.issue');
            Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
            Route::get('certificates/{id}', [CertificateController::class, 'show'])->name('certificates.show');
            Route::get('certificates/{id}/pdf', [CertificateController::class, 'pdf'])->name('certificates.pdf');
            Route::get('students/{id}/id-card/pdf', [IdCardController::class, 'student'])->name('students.id-card.pdf');
            Route::get('users/{id}/id-card/pdf', [IdCardController::class, 'staff'])->name('users.id-card.pdf');

            // ---- Notice Board (Phase 11) ------------------------------------------
            Route::get('notices', [NoticeController::class, 'index'])->name('notices.index');
            Route::get('notices/{notice}', [NoticeController::class, 'show'])->name('notices.show');
            Route::post('notices', [NoticeController::class, 'store'])->name('notices.store');
            Route::put('notices/{notice}', [NoticeController::class, 'update'])->name('notices.update');
            Route::delete('notices/{notice}', [NoticeController::class, 'destroy'])->name('notices.destroy');
            Route::post('notices/{notice}/publish', [NoticeController::class, 'publish'])->name('notices.publish');

            // ---- Communication / Notifications (Phase 11) --------------------------
            Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
            Route::get('notifications', [AppNotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/{id}/read', [AppNotificationController::class, 'markRead'])->name('notifications.read');
            Route::post('notifications/read-all', [AppNotificationController::class, 'markAllRead'])->name('notifications.read-all');

            // ---- Reports & Analytics (Phase 12) -------------------------------
            Route::get('reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
            Route::get('reports/academic-performance', [ReportController::class, 'academicPerformance'])->name('reports.academic-performance');
            Route::get('reports/enrollment', [ReportController::class, 'enrollment'])->name('reports.enrollment');
            Route::get('reports/operations', [ReportController::class, 'operations'])->name('reports.operations');

            // ---- Global Search (Phase 12) --------------------------------------
            Route::get('search', [SearchController::class, 'index'])->name('search');

            // ---- Parent portal -----------------------------------------------
            Route::get('parent/children', [ParentPortalController::class, 'children'])->name('parent.children');
            Route::get('parent/children/{student}/profile', [ParentPortalController::class, 'childProfile'])->name('parent.children.profile');
            Route::get('parent/children/{student}/attendance', [ParentPortalController::class, 'childAttendance'])->name('parent.children.attendance');
            Route::get('parent/children/{student}/exams', [ParentPortalController::class, 'childExams'])->name('parent.children.exams');
            Route::get('parent/children/{student}/report-card', [ParentPortalController::class, 'childReportCard'])->name('parent.children.report-card');
            Route::get('parent/children/{student}/report-card/pdf', [ParentPortalController::class, 'childReportCardPdf'])->name('parent.children.report-card.pdf');
            Route::get('parent/children/{student}/term-result', [ParentPortalController::class, 'childTermResult'])->name('parent.children.term-result');
            Route::get('parent/children/{student}/term-result/pdf', [ParentPortalController::class, 'childTermResultPdf'])->name('parent.children.term-result.pdf');
            Route::get('parent/children/{student}/homework', [ParentPortalController::class, 'childHomework'])->name('parent.children.homework');
            Route::get('parent/children/{student}/remarks', [ParentPortalController::class, 'childRemarks'])->name('parent.children.remarks');
            Route::get('parent/children/{student}/invoices', [ParentPortalController::class, 'childInvoices'])->name('parent.children.invoices');
        });
    });

});
