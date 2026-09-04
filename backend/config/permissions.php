<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Modules
    |--------------------------------------------------------------------------
    |
    | Every "module.action" permission the system knows about, grouped by
    | module. PermissionSeeder reads this to create the Permission rows, and
    | the frontend's role editor reads the equivalent list from GET
    | /api/v1/permissions to render its matrix. Add a module/action here
    | first when a new module needs authorization.
    |
    */

    'modules' => [
        'schools' => ['manage'],
        'users' => ['view', 'create', 'edit', 'delete', 'manage-mfa', 'import', 'export'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'edit'],
        'audit-logs' => ['view', 'manage'],
        // Phase 15 — admin bulk "export the whole school's data"
        // (data-export.school). Self-service "export my own data" is
        // deliberately ungated, same self-service shape as leave requests/
        // payslips — see DataExportController.
        'data-export' => ['school'],
        'academic-years' => ['view', 'create', 'edit', 'delete'],
        'academic-structure' => ['view', 'create', 'edit', 'delete', 'import'],
        'timetable' => ['view', 'create', 'edit', 'delete'],
        'students' => ['view', 'create', 'edit', 'delete', 'import', 'export'],
        'guardians' => ['view', 'create', 'edit', 'delete', 'import'],
        'enrollment' => ['manage'],
        'student-attendance' => ['view', 'mark', 'edit', 'export'],
        'staff-attendance' => ['view', 'mark', 'edit', 'export'],
        'grading' => ['view', 'manage'],
        'exams' => ['view', 'create', 'edit', 'delete', 'publish'],
        'exam-marks' => ['view', 'enter', 'edit', 'export', 'publish', 'import'],
        'questions' => ['view', 'create', 'edit', 'delete', 'import'],
        'online-exams' => ['view', 'configure'],
        'exam-timetable' => ['view', 'edit'],
        'homework' => ['view', 'create', 'edit', 'delete', 'grade'],
        'remarks' => ['view', 'create', 'edit', 'delete'],
        // Course Resource Library — teacher-uploaded materials (documents/
        // links/videos) scoped by section+subject, same shape as homework.
        // Marking your own view/completion progress needs no permission,
        // same self-service shape as homework submission.
        'course-materials' => ['view', 'create', 'edit', 'delete'],
        'dashboard' => ['view'],
        // Cross-tenant, Super Admin only — managing the platform itself
        // (all schools' plans/subscriptions), not a single school's own
        // data. Distinct from the school-scoped 'billing' module below.
        'platform' => ['view-tenants', 'manage-billing', 'view-metrics', 'offboard-schools'],
        // School-scoped — a School Admin's own plan/trial/usage and a link
        // out to Stripe's hosted portal. Not the school-to-parent tuition
        // billing below (that's 'fees'/'invoices', a separate concern).
        'billing' => ['view', 'manage'],
        // Phase 8 — school-to-parent billing config: fee categories and
        // fee structures (the templates invoices are generated from).
        'fees' => ['view', 'create', 'edit', 'delete'],
        // Phase 8 — the transactional side: invoices, recording payments
        // against them (currently always the 'manual' PaymentGatewayInterface
        // implementation — cash/cheque/bank-transfer/etc. recorded by staff;
        // a real online gateway is a deliberate future integration, not
        // built here), issuing credit notes/refunds, and financial reports.
        'invoices' => ['view', 'create', 'edit', 'delete', 'void', 'record-payment', 'issue-credit-note', 'view-reports'],
        // Phase 9 — job-title lookup CRUD (also covers assigning it to a user).
        'designations' => ['view', 'create', 'edit', 'delete'],
        // Phase 9 — leave types (config) + requests. 'view' sees every staff
        // member's requests; 'manage' additionally covers leave-type CRUD and
        // approve/reject. Submitting/viewing *your own* leave requests is
        // deliberately not gated by any permission here — same self-service
        // shape as staff-attendance's self check-in, see LeaveRequestPolicy.
        'leave' => ['view', 'manage'],
        // Phase 9 — salary structures + payslip generation. 'view' sees every
        // staff member's payslips/structures; 'manage' covers creating salary
        // structures, generating payslips, and marking them paid. Deliberately
        // NOT granted to Principal/Management by default — salary data is
        // sensitive in a way attendance/leave aren't, see SchoolProvisioningService.
        // Viewing *your own* payslips is likewise ungated.
        'payroll' => ['view', 'manage'],
        // Phase 10 — books catalog + issue/return. No self-service angle
        // (unlike leave/payroll) — a Librarian manages this on behalf of
        // students/staff, nobody views/checks out their own record here.
        'library' => ['view', 'manage'],
        // Phase 10 — vehicles, routes/stops, student assignments.
        'transport' => ['view', 'manage'],
        // Phase 10 — hostels, rooms, student allocations.
        'hostel' => ['view', 'manage'],
        // Phase 10 — visitor log (check-in/check-out at the front desk).
        'front-desk' => ['view', 'manage'],
        // Phase 11 — certificate templates (CRUD) plus issuing one to a
        // student ('issue', bolted on the same way invoices.void is).
        // Viewing *your own* issued certificate is self-scoped for
        // Student/Parent via Certificate::scopeVisibleTo(), same shape as
        // invoices — they're granted certificates.view directly, not exempted.
        'certificates' => ['view', 'create', 'edit', 'delete', 'issue'],
        // Phase 11 — the public notice/events board. 'view' here means
        // "can see drafts and manage the board" (School Admin/Principal/
        // Management) — reading the *published* board is unpermissioned
        // for everyone else, same self-service shape as homework submission.
        // See NoticePolicy/Notice::scopeVisibleTo().
        'notice-board' => ['view', 'create', 'edit', 'delete', 'publish'],
        // Phase 11 — composing/sending a broadcast (in-app/email/SMS/push,
        // see App\Contracts\SmsGatewayInterface/PushGatewayInterface).
        // Reading *your own* inbox (AppNotification) needs no permission at
        // all, same self-service shape as leave requests/payslips.
        'communication' => ['view', 'manage'],
    ],

];
