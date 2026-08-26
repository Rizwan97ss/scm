# Database

MySQL in production, SQLite for local dev/tests — migrations are written to
be compatible with both (no MySQL-only or SQLite-only column types/features).
See [setup.md](setup.md) for switching connections and [deployment.md](deployment.md)
for the MySQL cutover.

## Migration order

```
schools
plans (added Phase 6; schools gains plan_id/stripe_id/pm_type/pm_last_four/
       trial_ends_at/billing_status via an additive migration afterward)
subscriptions, subscription_items (Laravel Cashier's own tables, published
       in Phase 6 Sub-phase B — schools.billing_status is a fast-read cache
       of subscriptions.stripe_status, not a replacement for it)
users, password_reset_tokens, sessions, personal_access_tokens
permission tables (roles, permissions, model_has_roles, model_has_permissions,
                    role_has_permissions — team-scoped by school_id)
activity_log
media
settings
id_sequences
academic_years
terms
departments
grade_levels
rooms
sections
subjects
class_subject_teacher
timetable_periods
timetable_entries
holidays
guardians
students
student_guardian
student_enrollment_histories
student_attendances
staff_attendances
grading_scales
grade_bands
exam_types (Phase 16)
assessment_component_types (Phase 16)
exams
exam_subject_groups (Phase 16)
exam_subjects
exam_marks
questions
question_options
online_test_questions
online_test_attempts
online_test_answers
homeworks (Phase 7)
homework_submissions (Phase 7)
student_remarks (Phase 7)
fee_categories (Phase 8)
fee_structures (Phase 8)
student_fee_assignments (Phase 8)
invoices (Phase 8)
invoice_items (Phase 8)
payments (Phase 8)
credit_notes (Phase 8)
designations (Phase 9)
users gains designation_id/employee_id/hire_date (Phase 9, additive)
leave_types (Phase 9)
leave_requests (Phase 9)
salary_structures (Phase 9)
payslips (Phase 9)
```

Every table above except `schools`, `settings` (global rows have
`school_id = null`), and the framework/permission tables carries a
non-nullable `school_id` foreign key, enforced additionally at the
application layer by `SchoolScope` (see [architecture.md](architecture.md#multi-tenancy)).

## Entity-relationship diagram

```mermaid
erDiagram
    SCHOOL ||--o{ USER : employs
    SCHOOL ||--o{ ACADEMIC_YEAR : has
    SCHOOL ||--o{ STUDENT : enrolls
    SCHOOL ||--o{ GUARDIAN : has
    SCHOOL ||--o{ SETTING : "overrides (nullable)"
    PLAN ||--o{ SCHOOL : "subscribed to (nullable)"
    SCHOOL ||--o{ SUBSCRIPTION : "bills (Cashier)"

    ACADEMIC_YEAR ||--o{ TERM : contains
    ACADEMIC_YEAR ||--o{ SECTION : offers
    ACADEMIC_YEAR ||--o{ STUDENT : "enrolled in"

    GRADE_LEVEL ||--o{ SECTION : "grouped into"
    SECTION ||--o{ STUDENT : "current section"
    SECTION }o--|| USER : "class teacher"
    SECTION }o--o| ROOM : "assigned room"

    SUBJECT ||--o{ CLASS_SUBJECT_TEACHER : "taught as"
    SECTION ||--o{ CLASS_SUBJECT_TEACHER : "taught in"
    USER ||--o{ CLASS_SUBJECT_TEACHER : teaches

    TIMETABLE_PERIOD ||--o{ TIMETABLE_ENTRY : "time slot for"
    SECTION ||--o{ TIMETABLE_ENTRY : has
    SUBJECT ||--o{ TIMETABLE_ENTRY : "subject of"
    USER ||--o{ TIMETABLE_ENTRY : "teacher of"

    STUDENT ||--o{ STUDENT_GUARDIAN : "linked via"
    GUARDIAN ||--o{ STUDENT_GUARDIAN : "linked via"
    GUARDIAN }o--o| USER : "portal account"
    STUDENT }o--o| USER : "portal account"

    STUDENT ||--o{ STUDENT_ENROLLMENT_HISTORY : "history of"
    USER ||--o{ STUDENT_ENROLLMENT_HISTORY : "performed by"

    STUDENT ||--o{ STUDENT_ATTENDANCE : "attendance of"
    SECTION ||--o{ STUDENT_ATTENDANCE : "taken for"
    TIMETABLE_PERIOD ||--o{ STUDENT_ATTENDANCE : "period (nullable)"
    USER ||--o{ STUDENT_ATTENDANCE : "marked by"

    USER ||--o{ STAFF_ATTENDANCE : "attendance of"
    USER ||--o{ STAFF_ATTENDANCE : "marked by"

    USER ||--o{ MODEL_HAS_ROLES : "assigned (team-scoped)"
    ROLE ||--o{ MODEL_HAS_ROLES : "grants"
    ROLE ||--o{ ROLE_HAS_PERMISSIONS : has
    PERMISSION ||--o{ ROLE_HAS_PERMISSIONS : "granted via"

    SCHOOL {
        bigint id PK
        string name
        string short_name
        string status
        bigint plan_id FK "nullable"
        string billing_status "nullable — raw Stripe status string"
        timestamp trial_ends_at "nullable"
    }
    PLAN {
        bigint id PK
        string key "unique — 'starter'/'growth'/'scale'"
        string name
        int price_cents
        int trial_days
        int max_students "nullable = unlimited"
        int max_staff "nullable = unlimited"
        bool is_active "deactivated, never deleted"
    }
    SUBSCRIPTION {
        bigint id PK
        bigint school_id FK "renamed from Cashier's stock user_id"
        string type "always 'default' in this app"
        string stripe_id "unique"
        string stripe_status "raw Stripe status — schools.billing_status mirrors this"
        timestamp trial_ends_at "nullable"
        timestamp ends_at "nullable — set when canceled"
    }
    USER {
        bigint id PK
        uuid uuid
        bigint school_id FK "nullable — null = Super Admin"
        string email
        string username
        string status
    }
    ACADEMIC_YEAR {
        bigint id PK
        bigint school_id FK
        string name
        date start_date
        date end_date
        bool is_current
        string status
    }
    SECTION {
        bigint id PK
        bigint school_id FK
        bigint academic_year_id FK
        bigint grade_level_id FK
        string name
        int capacity
        bigint class_teacher_id FK "nullable, → users"
        bigint room_id FK "nullable"
    }
    STUDENT {
        bigint id PK
        uuid uuid
        bigint school_id FK
        string admission_number "unique per school"
        bigint user_id FK "nullable — portal account"
        bigint current_grade_level_id FK "nullable"
        bigint current_section_id FK "nullable"
        bigint academic_year_id FK
        string status "active|inactive|transferred|withdrawn|graduated|alumni"
        date admission_date
    }
    GUARDIAN {
        bigint id PK
        uuid uuid
        bigint school_id FK
        bigint user_id FK "nullable — portal account"
        string first_name
        string last_name
        string phone
        timestamp invited_at
    }
    STUDENT_GUARDIAN {
        bigint id PK
        bigint student_id FK
        bigint guardian_id FK
        string relationship_type
        bool is_primary
        bool can_pickup
    }
    STUDENT_ENROLLMENT_HISTORY {
        bigint id PK
        bigint student_id FK
        bigint academic_year_id FK
        bigint from_grade_level_id FK "nullable"
        bigint to_grade_level_id FK "nullable"
        bigint from_section_id FK "nullable"
        bigint to_section_id FK "nullable"
        string action "admission|promotion|transfer|withdrawal|graduation|reactivation"
        date effective_date
        bigint performed_by FK "→ users"
    }
    SETTING {
        bigint id PK
        bigint school_id FK "nullable — null = global default"
        string key
        text value
        string type "string|integer|boolean|json"
        string group
        bool is_public
    }
    ID_SEQUENCE {
        bigint id PK
        bigint school_id FK
        string key
        bigint last_value
    }
    STUDENT_ATTENDANCE {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint section_id FK "section at time of marking, not necessarily current"
        bigint academic_year_id FK
        bigint timetable_period_id FK "nullable — null = whole-day record"
        date date
        string status "present|absent|late|half_day|excused|on_leave"
        text remarks "nullable"
        bigint marked_by FK "→ users"
    }
    STAFF_ATTENDANCE {
        bigint id PK
        bigint school_id FK
        bigint user_id FK
        date date
        string status
        time check_in_time "nullable"
        time check_out_time "nullable"
        text remarks "nullable"
        bigint marked_by FK "→ users"
    }
```

### Exams, grading & online tests

Kept as a second diagram rather than folded into the one above — it's a
self-contained subsystem (nothing outside it references these tables) and
the combined diagram was already at the edge of readable.

```mermaid
erDiagram
    GRADING_SCALE ||--o{ GRADE_BAND : "bands of"
    EXAM_TYPE ||--o{ EXAM : "typed as (nullable)"
    EXAM ||--o{ EXAM_SUBJECT_GROUP : "examines"
    SUBJECT ||--o{ EXAM_SUBJECT_GROUP : "subject of"
    SECTION ||--o{ EXAM_SUBJECT_GROUP : "section of"
    GRADING_SCALE ||--o{ EXAM_SUBJECT_GROUP : "scores against (nullable — falls back to school default)"

    EXAM_SUBJECT_GROUP ||--o{ EXAM_SUBJECT : "components of"
    ASSESSMENT_COMPONENT_TYPE ||--o{ EXAM_SUBJECT : "component type of"

    EXAM_SUBJECT ||--o{ EXAM_MARK : "marks for"
    STUDENT ||--o{ EXAM_MARK : "marks of"

    EXAM_SUBJECT ||--o{ ONLINE_TEST_QUESTION : "draws from"
    QUESTION ||--o{ ONLINE_TEST_QUESTION : "used in"
    QUESTION ||--o{ QUESTION_OPTION : "options of"

    EXAM_SUBJECT ||--o{ ONLINE_TEST_ATTEMPT : "attempted via"
    STUDENT ||--o{ ONLINE_TEST_ATTEMPT : attempts
    ONLINE_TEST_ATTEMPT ||--o{ ONLINE_TEST_ANSWER : "answers of"
    QUESTION ||--o{ ONLINE_TEST_ANSWER : "answered in"
    QUESTION_OPTION ||--o{ ONLINE_TEST_ANSWER : "selected in (nullable)"

    GRADING_SCALE {
        bigint id PK
        bigint school_id FK
        string name
        bool is_default
    }
    GRADE_BAND {
        bigint id PK
        bigint grading_scale_id FK
        decimal min_percentage
        decimal max_percentage
        string grade_label
        decimal grade_point "nullable"
        string remark "nullable"
    }
    EXAM_TYPE {
        bigint id PK
        bigint school_id FK
        string name "Class Test, Trimester, Final/Annual, ... — configurable"
        string code UK
        smallint sequence
        bool is_active
    }
    ASSESSMENT_COMPONENT_TYPE {
        bigint id PK
        bigint school_id FK
        string name "Online MCQ, Written, Practical, Oral/Viva, ... — configurable"
        string code UK
        bool is_auto_graded "drives UI: shows the online-test fields when adding a component of this type"
        smallint sequence
        bool is_active
    }
    EXAM {
        bigint id PK
        bigint school_id FK
        bigint academic_year_id FK
        bigint term_id FK "nullable"
        bigint exam_type_id FK "nullable"
        string name
        decimal weight "relative contribution to the term result, default 1"
        bool is_published
        timestamp published_at "nullable"
    }
    EXAM_SUBJECT_GROUP {
        bigint id PK
        bigint exam_id FK
        bigint subject_id FK
        bigint section_id FK
        bigint grading_scale_id FK "nullable"
        decimal passing_marks "nullable — applies to the subject's combined total"
        timestamp published_at "nullable — declares this subject's result independent of Exam.is_published"
        bigint published_by FK "nullable → users"
    }
    EXAM_SUBJECT {
        bigint id PK
        bigint exam_id FK
        bigint exam_subject_group_id FK "the subject-in-section-in-exam this component belongs under"
        bigint assessment_component_type_id FK
        bigint subject_id FK "kept here too (not group-only) so isTaughtBy()/existing queries needed no rewrite"
        bigint section_id FK "same reason"
        smallint sequence
        decimal max_marks
        date exam_date "nullable"
        bool is_online
        smallint duration_minutes "nullable"
        timestamp online_starts_at "nullable"
        timestamp online_ends_at "nullable"
        bool shuffle_questions
        tinyint max_attempts
    }
    EXAM_MARK {
        bigint id PK
        bigint exam_subject_id FK "one component's mark — a multi-component subject has several EXAM_MARK rows per student"
        bigint student_id FK
        decimal marks_obtained "nullable"
        bool is_absent
        text remarks "nullable"
        bigint entered_by FK "→ users — the student's own user_id when auto-graded"
    }
    QUESTION {
        bigint id PK
        bigint school_id FK
        bigint subject_id FK "nullable"
        string type "mcq | true_false — graded identically via QUESTION_OPTION"
        text text
        decimal default_marks
        decimal negative_marks "nullable — Phase 16, null = no negative marking"
        text explanation "nullable — shown only after grading"
        bigint created_by FK "→ users"
    }
    QUESTION_OPTION {
        bigint id PK
        bigint question_id FK
        text option_text
        bool is_correct
        smallint sequence
    }
    ONLINE_TEST_QUESTION {
        bigint id PK
        bigint exam_subject_id FK
        bigint question_id FK
        decimal marks "nullable — null = use question.default_marks"
        smallint sequence
    }
    ONLINE_TEST_ATTEMPT {
        bigint id PK
        bigint exam_subject_id FK
        bigint student_id FK
        tinyint attempt_number
        string status "in_progress | submitted"
        timestamp started_at
        timestamp submitted_at "nullable"
        decimal score "nullable"
        decimal max_score "nullable"
    }
    ONLINE_TEST_ANSWER {
        bigint id PK
        bigint attempt_id FK
        bigint question_id FK
        bigint selected_option_id FK "nullable"
        bool is_correct "nullable — set at submit time"
        decimal marks_awarded "nullable"
    }
```

### Teacher module: homework & remarks (Phase 7)

```mermaid
erDiagram
    SECTION ||--o{ HOMEWORK : "assigned to"
    SUBJECT ||--o{ HOMEWORK : "subject of"
    USER ||--o{ HOMEWORK : "assigned by"

    HOMEWORK ||--o{ HOMEWORK_SUBMISSION : "submissions of"
    STUDENT ||--o{ HOMEWORK_SUBMISSION : submits
    USER ||--o{ HOMEWORK_SUBMISSION : "graded by (nullable)"

    STUDENT ||--o{ STUDENT_REMARK : "remarks about"
    USER ||--o{ STUDENT_REMARK : authors
    SECTION ||--o{ STUDENT_REMARK : "section at time of writing (nullable)"

    HOMEWORK {
        bigint id PK
        bigint school_id FK
        bigint academic_year_id FK
        bigint section_id FK
        bigint subject_id FK
        bigint teacher_id FK "→ users"
        string title
        text description "nullable"
        date due_date
        decimal max_score "nullable"
    }
    HOMEWORK_SUBMISSION {
        bigint id PK
        bigint school_id FK
        bigint homework_id FK
        bigint student_id FK
        string status "submitted | graded"
        text content "nullable"
        timestamp submitted_at "nullable — null if teacher-graded without a submit"
        decimal score "nullable"
        text feedback "nullable"
        timestamp graded_at "nullable"
        bigint graded_by FK "nullable, → users"
    }
    STUDENT_REMARK {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint author_id FK "→ users"
        bigint section_id FK "nullable"
        string category "academic | behavioral | general"
        text body
        bool visible_to_guardian "default true — gates Parent visibility only, not Student's own"
    }
```

### Fees, invoices & payments (Phase 8)

```mermaid
erDiagram
    FEE_CATEGORY ||--o{ FEE_STRUCTURE : "categorizes"
    ACADEMIC_YEAR ||--o{ FEE_STRUCTURE : "billed within"
    GRADE_LEVEL ||--o{ FEE_STRUCTURE : "applies to (nullable = all grades)"

    FEE_STRUCTURE ||--o{ STUDENT_FEE_ASSIGNMENT : "discounted by"
    STUDENT ||--o{ STUDENT_FEE_ASSIGNMENT : "discount for"

    STUDENT ||--o{ INVOICE : "billed to"
    ACADEMIC_YEAR ||--o{ INVOICE : "billed within"
    INVOICE ||--o{ INVOICE_ITEM : "line items"
    FEE_CATEGORY ||--o{ INVOICE_ITEM : categorizes
    FEE_STRUCTURE ||--o{ INVOICE_ITEM : "generated from (nullable — ad-hoc items have none)"

    INVOICE ||--o{ PAYMENT : "paid via"
    INVOICE ||--o{ CREDIT_NOTE : "credited via"

    FEE_CATEGORY {
        bigint id PK
        bigint school_id FK
        string name
        bool is_active
    }
    FEE_STRUCTURE {
        bigint id PK
        bigint school_id FK
        bigint academic_year_id FK
        bigint grade_level_id FK "nullable — null = all grades"
        bigint fee_category_id FK
        string name
        decimal amount
        string frequency "one_time | monthly | quarterly | term | annual — informational, no auto-billing scheduler"
        tinyint due_day_of_month "nullable"
        bool is_active
    }
    STUDENT_FEE_ASSIGNMENT {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint fee_structure_id FK
        string discount_type "none | percentage | fixed"
        decimal discount_value
        string reason "nullable"
    }
    INVOICE {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint academic_year_id FK
        string invoice_number "unique per school, via IdSequenceService"
        date issue_date
        date due_date
        string status "draft | issued | partially_paid | paid | void"
        decimal subtotal
        decimal discount_total
        decimal total
        decimal amount_paid "denormalized — recomputed by InvoiceService::recalculate()"
        decimal credit_total "denormalized — recomputed alongside amount_paid"
        bigint created_by FK "→ users"
    }
    INVOICE_ITEM {
        bigint id PK
        bigint school_id FK
        bigint invoice_id FK
        bigint fee_structure_id FK "nullable"
        bigint fee_category_id FK
        string description
        int quantity
        decimal unit_amount
        decimal amount
    }
    PAYMENT {
        bigint id PK
        bigint school_id FK
        bigint invoice_id FK
        bigint student_id FK
        string payment_number "unique per school, via IdSequenceService"
        decimal amount
        string method "cash | cheque | bank_transfer | upi | card | other"
        string gateway "always 'manual' in v1 — see PaymentGatewayInterface"
        string reference_number "nullable"
        date paid_at
        bigint received_by FK "→ users"
    }
    CREDIT_NOTE {
        bigint id PK
        bigint school_id FK
        bigint invoice_id FK
        string credit_note_number "unique per school, via IdSequenceService"
        decimal amount
        text reason
        bigint issued_by FK "→ users"
        date issued_at
    }
```

### Staff / HR: designations, leave & payroll (Phase 9)

```mermaid
erDiagram
    DESIGNATION ||--o{ USER : "held by (nullable)"
    LEAVE_TYPE ||--o{ LEAVE_REQUEST : categorizes
    USER ||--o{ LEAVE_REQUEST : "filed by"
    USER ||--o{ LEAVE_REQUEST : "reviewed by (nullable)"
    USER ||--o{ SALARY_STRUCTURE : "pay components for"
    USER ||--o{ PAYSLIP : "issued to"
    SALARY_STRUCTURE ||--o{ PAYSLIP : "snapshotted into (nullable)"

    DESIGNATION {
        bigint id PK
        bigint school_id FK
        string name
        bool is_active
    }
    LEAVE_TYPE {
        bigint id PK
        bigint school_id FK
        string name
        smallint days_allowed_per_year "nullable = unlimited"
        bool is_paid
        bool is_active
    }
    LEAVE_REQUEST {
        bigint id PK
        bigint school_id FK
        bigint user_id FK "the staff member"
        bigint leave_type_id FK
        date start_date
        date end_date
        smallint days "computed inclusive date diff"
        text reason
        string status "pending | approved | rejected | cancelled"
        bigint reviewed_by FK "nullable, → users"
        timestamp reviewed_at "nullable"
    }
    SALARY_STRUCTURE {
        bigint id PK
        bigint school_id FK
        bigint user_id FK
        decimal basic_salary
        decimal allowances
        decimal deductions
        date effective_from
        date effective_to "nullable — set when superseded"
        bool is_active "only one true per user at a time"
    }
    PAYSLIP {
        bigint id PK
        bigint school_id FK
        bigint user_id FK
        bigint salary_structure_id FK "nullable"
        string payslip_number "unique per school, via IdSequenceService"
        tinyint month
        smallint year
        decimal basic_salary "snapshotted, not a live join"
        decimal allowances "snapshotted"
        decimal deductions "snapshotted"
        decimal net_salary "snapshotted"
        string status "generated | paid"
        date paid_at "nullable"
    }
```

### Library, Transport, Hostel & Front Desk (Phase 10)

```mermaid
erDiagram
    BOOK ||--o{ BOOK_ISSUE : "issued as"
    STUDENT ||--o{ BOOK_ISSUE : "borrowed by (nullable)"
    USER ||--o{ BOOK_ISSUE : "borrowed by (nullable)"
    USER ||--o{ BOOK_ISSUE : "issued by"

    ROUTE ||--o{ ROUTE_STOP : "stops along"
    STUDENT ||--o{ STUDENT_TRANSPORT_ASSIGNMENT : "assigned via"
    ROUTE ||--o{ STUDENT_TRANSPORT_ASSIGNMENT : "on"
    ROUTE_STOP ||--o{ STUDENT_TRANSPORT_ASSIGNMENT : "boards at"
    VEHICLE ||--o{ STUDENT_TRANSPORT_ASSIGNMENT : "rides (nullable)"

    HOSTEL ||--o{ HOSTEL_ROOM : "contains"
    HOSTEL_ROOM ||--o{ HOSTEL_ALLOCATION : "occupied via"
    STUDENT ||--o{ HOSTEL_ALLOCATION : "allocated via"

    USER ||--o{ VISITOR : "logged by"

    BOOK {
        bigint id PK
        bigint school_id FK
        string title
        string author "nullable"
        string isbn "nullable"
        string category "nullable"
        int total_copies
        int available_copies "denormalized — shifted by issue/return/edit"
        bool is_active
    }
    BOOK_ISSUE {
        bigint id PK
        bigint school_id FK
        bigint book_id FK
        bigint student_id FK "nullable — exactly one of student_id/user_id"
        bigint user_id FK "nullable — exactly one of student_id/user_id"
        bigint issued_by FK "→ users"
        date issue_date
        date due_date
        date return_date "nullable"
        decimal fine_amount "computed on return, school.library.fine_per_day * days late"
        string status "issued | returned | overdue"
    }
    VEHICLE {
        bigint id PK
        bigint school_id FK
        string registration_number "unique per school"
        int capacity
        string driver_name "nullable"
        string driver_phone "nullable"
        bool is_active
    }
    ROUTE {
        bigint id PK
        bigint school_id FK
        string name "unique per school"
        text description "nullable"
        bool is_active
    }
    ROUTE_STOP {
        bigint id PK
        bigint school_id FK
        bigint route_id FK
        string name
        smallint sequence
    }
    STUDENT_TRANSPORT_ASSIGNMENT {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint route_id FK
        bigint route_stop_id FK
        bigint vehicle_id FK "nullable"
        date effective_from
        bool is_active "only one true per student at a time"
    }
    HOSTEL {
        bigint id PK
        bigint school_id FK
        string name "unique per school"
        string type "boys | girls | mixed"
        string address "nullable"
        string warden_name "nullable"
        string warden_phone "nullable"
        bool is_active
    }
    HOSTEL_ROOM {
        bigint id PK
        bigint school_id FK
        bigint hostel_id FK
        string room_number
        int capacity
        bool is_active
    }
    HOSTEL_ALLOCATION {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint hostel_room_id FK
        string bed_number "nullable"
        date allocated_date
        date vacated_date "nullable"
        string status "allocated | vacated"
    }
    VISITOR {
        bigint id PK
        bigint school_id FK
        string name
        string phone "nullable"
        string purpose
        string whom_to_meet "nullable"
        timestamp check_in_time
        timestamp check_out_time "nullable"
        text notes "nullable"
        bigint logged_by FK "→ users"
    }
```

### Certificates, Notice Board & Communication (Phase 11)

```mermaid
erDiagram
    CERTIFICATE_TEMPLATE ||--o{ CERTIFICATE : "rendered into"
    STUDENT ||--o{ CERTIFICATE : "issued to"
    USER ||--o{ CERTIFICATE : "issued by"

    USER ||--o{ NOTICE : "created by"

    USER ||--o{ ANNOUNCEMENT : "sent by"
    ANNOUNCEMENT ||--o{ APP_NOTIFICATION : "fans out to"
    USER ||--o{ APP_NOTIFICATION : "received by"

    CERTIFICATE_TEMPLATE {
        bigint id PK
        bigint school_id FK
        string name "unique per school"
        string type "free text, e.g. Bonafide, Transfer"
        text body "placeholders: {{student_name}}, {{admission_number}}, ..."
        bool is_active
    }
    CERTIFICATE {
        bigint id PK
        bigint school_id FK
        bigint student_id FK
        bigint certificate_template_id FK
        string certificate_number "unique per school, via IdSequenceService"
        date issued_date
        bigint issued_by FK "→ users"
        text content "rendered snapshot — never re-rendered if the template changes later"
    }
    NOTICE {
        bigint id PK
        bigint school_id FK
        string title
        text body
        string type "general | event"
        string audience "all | students | staff | parents"
        date event_date "nullable — event type only"
        time start_time "nullable"
        time end_time "nullable"
        string location "nullable"
        bool is_published
        timestamp published_at "nullable"
        date expires_at "nullable"
        bigint created_by FK "→ users"
    }
    ANNOUNCEMENT {
        bigint id PK
        bigint school_id FK
        string title
        text body
        string audience "all | students | staff | parents"
        json channels "subset of in_app | email | sms | push"
        int recipient_count "denormalized — snapshotted at send time"
        bigint sent_by FK "→ users"
        timestamp sent_at
    }
    APP_NOTIFICATION {
        bigint id PK
        bigint school_id FK
        bigint user_id FK "the recipient"
        bigint announcement_id FK "nullable — null for a future system-generated notification with no announcement behind it"
        string title
        text body
        bool is_read
        timestamp read_at "nullable"
    }
```

**ID cards are not a table.** Unlike a `Certificate`, an ID card is never
issued or stored — `GET /students/{id}/id-card/pdf` and
`GET /users/{id}/id-card/pdf` render straight from the live `Student`/`User`
row on every request. There's nothing to snapshot (an ID card should
always show current data) and nothing to audit (it's not a legal
document), so a stored record would just be a cache with no expiry
policy — not worth the complexity for what's a render.

## Key modeling decisions

**UUIDs alongside auto-increment IDs.** `User` and `Student` (and other
externally-referenced models) carry both a `bigint` primary key (fast joins,
internal use) and a `uuid` column (unique, indexed — used wherever an
identifier might be exposed externally, e.g. QR codes on ID cards in a
later phase). Auto-generated in a model `booted()` hook, not left to the
database, so it's available before the first save.

**Admission numbers are school-scoped sequential, not global.** `students.admission_number`
is unique per `(school_id, admission_number)`, not globally unique — two
schools can both have admission number `2026-0001`. Generation goes through
`IdSequenceService` (backed by `id_sequences`, a per-school-per-key
row-locked counter) rather than `MAX(id)+1`, which would race under
concurrent admissions. The format itself (`{YEAR}-{SEQ}` by default, 4-digit
padding) is a DB-driven setting (`students.admission_number_format`,
`students.admission_number_padding`), not hardcoded — see
[configuration.md](configuration.md).

**Enrollment is an append-only history, not just a status field.**
`students.status` (and `current_grade_level_id`/`current_section_id`) is a
denormalized "current state" for fast queries, but every transition
(admission, promotion, transfer, withdrawal, graduation, reactivation) also
writes an immutable row to `student_enrollment_histories` with the
before/after grade+section, a reason, an effective date, and who performed
it. This is what powers the "Enrollment History" timeline tab on a student
profile and is the audit trail a school actually needs (regulators and
parents ask "when did this happen and who approved it," not just "what's
the current status"). `App\Services\StudentEnrollmentService` is the only
place that's allowed to write both sides atomically — don't update
`students.status` directly from a controller.

**Guardians and Students both optionally link to a `User`.** A `Student` or
`Guardian` row can exist with no portal account at all (most schools admit
students before anyone sets up a login), and `user_id` is populated later
via the "Invite to portal" action. This is why `Student`/`Guardian` are
separate tables from `User` rather than the person's portal identity and
school record being the same row — a school needs to record a student who
will never log in (too young, no parent wants a portal account) without
that being a data-modeling problem.

**Soft deletes on schools, users, academic years, sections, students,
guardians** — anything a school would need to "undo" a deletion of, or that
historical records (enrollment history, activity log) still reference after
removal. Reference/lookup tables that don't accumulate history
(departments, grade levels, subjects, rooms, holidays, timetable entries)
don't soft-delete; they're just deleted.

**Teacher double-booking is a validation rule, not a DB constraint.**
`timetable_entries.teacher_id` is nullable (a period can have no assigned
teacher yet), so "no teacher double-booked in the same period" can't be a
simple unique index — it's enforced in `StoreTimetableEntryRequest`/
`UpdateTimetableEntryRequest` by querying existing entries for that
teacher+period+day before allowing the write.

**Attendance marking is an upsert, keyed on (student|user, date[, period]),
never a plain insert.** Re-submitting a roster (a teacher fixing a mis-click
before end of day) corrects the existing `student_attendances`/
`staff_attendances` row instead of creating a duplicate. `section_id` on
`student_attendances` is stamped at the time attendance is taken, not looked
up live from the student's *current* section — a mid-year section change
shouldn't rewrite what section a January absence was recorded against.

**`date`-cast columns silently store a full `Y-m-d H:i:s` value on SQLite,
not just `Y-m-d`.** Eloquent's `date` cast writes through the connection's
one uniform date format on save (`HasAttributes::fromDateTime()` doesn't
special-case the `date` cast vs `datetime`) — MySQL's native `DATE` column
type truncates the time part back off at the SQL layer on write, but
SQLite's dynamic typing does not, so the raw stored value differs by
database driver even though both use the exact same Eloquent code path. A
plain `where('date', $string)` or `whereBetween('date', [...])` therefore
silently matches zero rows on SQLite (dev/test) while working fine on MySQL
(production) — exactly the kind of driver-specific bug that's invisible
until you manually click through the feature against the real dev database.
This shipped once, in four places (`AttendanceService`'s upserts, both
attendance controllers' `date` query-builder filter, and
`DashboardService`'s "today" counts) before being fixed uniformly with
`whereDate()`, which compares the date portion regardless of what's stored.
**Any new code touching a `date`-cast column must use `whereDate()`, never
a raw string comparison** — see `App\Services\AttendanceService`'s docblock,
which carries this warning at the source.

**Grades are computed on read, never stored.** `exam_marks` holds only
`marks_obtained`; the letter grade, GPA point, and remark are resolved at
request time by summing a subject group's component marks against its
`max_marks` total and looking the percentage up in the applicable
`grading_scales`' `grade_bands` (`GradingScale::resolveBand()`, called
from `SubjectResultService::forGroup()`). This means editing a grading
scale's bands retroactively changes how *every* already-entered mark is
displayed — a deliberate tradeoff (a school correcting a lenient boundary
should see it reflected everywhere at once) rather than a bug; if a
school ever needs "the grade as it stood on the day it was published" to
be immutable, that's a snapshotting feature to add later, not the current
behavior.

**Phase 16 split `ExamSubject` into a group + components.** Before Phase
16, `ExamSubject` was "the whole subject" — one row per subject-in-
section-in-exam, one `ExamMark` each. A subject's result now often
combines several independently-graded pieces (Online MCQ auto-graded,
Written/Practical/Oral entered by a teacher), so a new
`exam_subject_groups` table now owns that subject-in-section-in-exam grain
(and what used to live on `ExamSubject`: `grading_scale_id`,
`passing_marks`, now `published_at`/`published_by` too), while
`ExamSubject` becomes **one gradable component** under a group — a 4-part
subject is one `exam_subject_groups` row with four `exam_subjects` rows
(`exam_subject_group_id`, `assessment_component_type_id`) under it, each
with its own `max_marks` and its own `ExamMark` per student. A null
`exam_subject_groups.grading_scale_id` falls back to the school's
`is_default` scale, same as before (see `SubjectResultService::forGroup()`).
Deliberately **not** a new parallel hierarchy: `exam_subjects.subject_id`/
`section_id` were kept (not moved to the group-only), specifically so
`ExamSubject::isTaughtBy()` and every existing marks-entry/online-test
query built around them needed no rewrite.

**Online tests reuse `ExamSubject` as their container instead of a parallel
"online exam" hierarchy.** `is_online`, `duration_minutes`,
`online_starts_at/ends_at`, `shuffle_questions`, and `max_attempts` are
just more columns on the same table an offline subject uses — the only
behavioral fork is whether marks come from a human (`ExamMarkController`)
or from `OnlineExamService::submitAttempt()` grading `online_test_answers`
automatically. Both paths write to the same `exam_marks` table, so report
cards and term results never need to know which one produced a given row.

**`online_test_questions` is a pivot with its own `marks` override**
(nullable) rather than always trusting `questions.default_marks` —
`OnlineTestQuestion::effectiveMarks()` resolves the override-or-default at
grading time. The override exists for the bulk-replace endpoint (`POST
.../online-test-questions`, still used by tests and available as a raw
primitive), but the frontend's actual create/import flow never sets it,
attaching with `marks: null` instead — each test's questions are authored
directly into it (`QuestionController::attachToTest()` /
`McqQuestionsImport`'s optional `$examSubject`), not picked from a
subject-wide pool and reused across tests at different point values. A
fresh test's question list (`GET .../online-test-questions`) is scoped to
just what's attached to that one `ExamSubject`, and starts empty.

**MCQ and True/False are graded through one mechanism, not two.**
`questions.type` is stored for UI/filtering purposes only — grading always
works by finding whichever `question_options` row has `is_correct = true`
and comparing it against the student's `selected_option_id`. A True/False
question is just an MCQ with exactly two options ("True"/"False"). This is
why the type list was kept to these two in Phase 5: both reduce to the same
single-correct-option comparison, which is what makes them the auto-gradable
half of "online examination" — a short-answer or essay type would need a
genuinely different (manual-grading) code path, deliberately deferred.

**Negative marking (Phase 16) lives on `questions.negative_marks`, nullable
— null means "no negative marking," today's original behavior, unchanged.**
A wrong-but-answered question costs `negative_marks`; a genuinely blank
one always scores 0, never penalized. `OnlineTestAnswer.marks_awarded`
keeps the real (possibly negative) per-question value for the review
breakdown; only the summed `OnlineTestAttempt.score` floors at 0 —
per-question transparency isn't sacrificed for a total that never
displays negative.

**Attempts nobody ever submits are backstopped server-side, not just by
the client-side countdown.** `TakeOnlineTestPage.tsx`'s timer is the
primary mechanism for a student still on the page; a closed tab or lost
connection leaves an `in_progress` attempt (consuming a `max_attempts`
slot) that the scheduled `exams:auto-submit-expired` command (Phase 16,
`OnlineExamService::autoSubmitExpired()`) sweeps up every five minutes,
scoring whatever was answered before the student disappeared through the
same `submitAttempt()` path a real submission takes.

**A submitted `online_test_answer`'s `is_correct`/`marks_awarded` are
snapshotted at grading time, not recomputed on every read.** If a question's
options are edited after an attempt has already been submitted and graded,
that attempt's historical answers keep whatever was true when it was
graded — only the *live* correctness key (used for grading *new* attempts)
changes. See `QuestionController::syncOptions()`'s docblock for the one
place this snapshotting is incomplete (`selected_option_id` itself, not the
score, can null out via the FK's `nullOnDelete` if a question's options are
edited after use — accepted as a v1 limitation).

**A `term_result` is never stored — always computed from `exams.weight` ×
each exam's `overall_percentage`.** `TermResultService` sums
`percentage * weight` across every *published* exam in a term that has at
least one graded mark for the student, divided by the sum of weights of
just those exams (not all exams in the term) — so a student who transferred
in and missed the Midterm is scored on the Final alone, not penalized with
a zero for the exam they were never enrolled for. Rank is computed the same
way for every other student in the same *current* section, which is a
simplification worth knowing about: it ranks against today's section
roster, not the roster as it stood at exam time.

**Plans are platform-level, not school-scoped — every school reads the
same shared set, none owns or edits its own copy.** `plans` deliberately
has no `BelongsToSchool`/`school_id`; a school's `plan_id` just points at
whichever row it subscribed to. Plans are deactivated (`is_active =
false`), never deleted, since a school that subscribed to a
now-retired tier still needs its historical `plan_id` to resolve.

**`schools.billing_status` stores Stripe's own raw subscription status
string directly** (`trialing`/`active`/`past_due`/`canceled`/`unpaid`/
etc.), not a custom enum translated from it — the Stripe webhook handler
(Sub-phase B) becomes a plain pass-through instead of a mapping layer
that can silently drift out of sync with what Stripe actually reports.
`null` means "not yet subscribed" for a brand-new signup mid-flow, or
"pre-dates the SaaS layer" for the schools that existed before Phase 6
(explicitly backfilled to `'active'` in the same migration that added
the column, so they're never accidentally locked out once later
sub-phases add access gating on this field).

**A plan's seat limits are projected into per-school `Settings`, not
read via a live join to `plans` on every request.**
`SchoolProvisioningService::applyPlanLimits()` writes
`billing.max_students`/`billing.max_staff`/`billing.plan_key` as
school-scoped `Setting` rows (group `billing`) at provisioning time (and
again on any plan change) — reusing the same global/per-school-override
`SettingsService` every other configurable behavior in the app already
goes through, rather than inventing a second "read a limit" code path.

**Seat-limit enforcement reads the projected `Settings`, never a live
join to `plans`** — `PlanLimitService` (Sub-phase E) counts current usage
against `billing.max_students`/`billing.max_staff` the same way
`EnsureSchoolIsUsable` middleware reads `schools.billing_status`
directly rather than re-deriving it from Cashier's `subscriptions` table
on every request: both are fast-read caches kept in sync by the
provisioning service and the Stripe webhook handler respectively, not
computed per-request. Limit violations throw a dedicated
`PlanLimitExceededException`, rendered as HTTP 402 — deliberately a
different status than 422 (validation) or 403 (permission), so the
frontend's toast handler and any future "upgrade your plan" CTA can key
off status code alone rather than parsing the message.

**`homeworks` is a single-level entity, unlike `exams`/`exam_subjects`'
two-level split.** An Exam groups multiple ExamSubjects (one exam period,
several subjects/sections) because marks entry and report-card generation
need that grouping. A homework assignment doesn't — it's inherently one
section, one subject, one teacher, one due date — so `Homework` carries
`section_id`/`subject_id`/`teacher_id` directly rather than introducing a
parallel `HomeworkAssignment`/`HomeworkDetail` split that would only add
a join for no real benefit. `Homework::isTaughtBy()` and
`scopeVisibleTo()` are still copied from `ExamSubject`'s exact pattern
(same "does this Teacher actually teach this section+subject" check,
same Student/Parent/Teacher branching), since row-level scoping doesn't
depend on how many levels the entity has.

**`homework_submissions` is upserted, never append-only.** Unique on
`(homework_id, student_id)` — a student resubmitting before grading
replaces the previous `content`/attachment rather than accumulating a
history of attempts (unlike `online_test_attempts`, which intentionally
keeps every attempt for a timed, potentially-multi-attempt online test).
A submission row can also exist with `submitted_at = null` if a teacher
grades work done outside the app (e.g. in class) without the student
ever using the submit flow — `status`/`score`/`feedback` don't depend on
a submission having actually happened through this endpoint.

**`student_remarks.visible_to_guardian` gates Parent visibility only,
never Student's own.** `StudentRemark::scopeVisibleTo()` lets a Student
see every remark about themselves regardless of this flag — the flag
exists to let a Teacher/Admin write a remark meant for internal staff
eyes only (e.g. an escalation note before a parent conversation happens),
not to hide feedback from the student it's about. Only the Parent-facing
query (`ParentPortalController::childRemarks()`) filters on it.

**Laravel Cashier's published `subscriptions` migration ships with a
hardcoded `user_id` column — renamed to `school_id` in this app, not left
as-is.** Cashier's `Subscription::owner()` and `Billable::subscriptions()`
both resolve the foreign-key column name *dynamically*, via
`(new Cashier::$customerModel)->getForeignKey()` — the literal `user_id`
in the package's own migration file is just what that resolves to in the
package's default configuration (billable = `User`). Since
`Cashier::useCustomerModel(School::class)` is set in
`AppServiceProvider::boot()`, the actual column Cashier queries at
runtime is `school_id`; the migration is edited to match rather than
relying on an FK column name that would silently never match what
Cashier's own relations look for.

**`invoices.amount_paid`/`credit_total` are denormalized, recomputed
transactionally, never hand-set from a controller.** `InvoiceService::recalculate()`
is the single place that sums `payments`/`credit_notes` and derives
`status` (`issued → partially_paid → paid`, or `void`) — called after every
`recordPayment()`/`issueCreditNote()`. This mirrors the `students.status` /
`student_enrollment_histories` pattern (a fast denormalized read, written
only through one service) rather than computing the sum on every read.

**Invoice/payment/credit-note numbers reuse `IdSequenceService` exactly as
`StudentIdGeneratorService` does for admission numbers** — `IdSequenceService`'s
own docblock named this as the intended future use back in Phase 0.
`FeeNumberService` adds no new counter mechanism, just three more
DB-driven format settings (`fees.invoice_number_format` etc., group
`fees`) following `students.admission_number_format`'s exact convention.

**Bulk invoice generation is one transaction per student, not one
transaction around the whole batch.** Besides being better bulk-operation
semantics (one student's failure doesn't roll back invoices already
correctly generated for earlier students), a single long transaction
issuing 3+ sequential writes reproduced a real, deterministic "unable to
open database file" (SQLite error 14) specifically under `php artisan
serve` on Windows — the identical code succeeded every time via Tinker
(no HTTP/middleware layer) and fails on the second write every time
through an actual request, regardless of `busy_timeout`/WAL settings. See
`InvoiceService::generateFromStructure()`'s docblock and
[testing.md](testing.md#manualbrowser-smoke-testing) for the full
diagnosis — this only affects SQLite dev/test, not MySQL production, but
blocked local testing until fixed, so it's recorded here as a real
constraint on how multi-write bulk operations should be structured.

**A `Payment`/`CreditNote` is never updated or deleted, only created.**
Correcting a mistaken payment happens via a credit note, not an edit —
matches real accounting practice (an immutable ledger entry) and is why
neither model has an `update`/`destroy` route at all, unlike every other
Phase 8 entity.

**HR fields live directly on `users`, not a parallel `staff_profiles`
table.** `designation_id`/`employee_id`/`hire_date` were added via a purely
additive migration (all nullable, no backfill needed) rather than a new
1:1 table — "Staff/HR concerns Users, not a new entity" was the deciding
framing, and it matches how `schools` itself gained Phase 6's billing
columns the same way. `Designation` is otherwise a plain lookup table
(job title, e.g. "Math Teacher"), structurally identical to `Department`.

**A `SalaryStructure` is closed, not deleted, when superseded.**
Creating a new one for a user sets the previous row's `is_active = false`
and `effective_to` to the new one's `effective_from` rather than deleting
it — a historical `Payslip.salary_structure_id` must still resolve to
whatever was in effect when that payslip was generated. Only one
`is_active = true` row per user is enforced in `PayrollService`, not a DB
constraint (same reasoning as `students.status`/enrollment history: the
"current" row is a fast-read projection, not the only copy of the truth).

**A `Payslip` snapshots its `SalaryStructure`'s amounts at generation
time — it never joins back to read them live.** If a salary structure
changes after a payslip already exists for a past month, that payslip's
figures must stay exactly what was actually paid; only newly-generated
payslips pick up the new amounts. Same rationale as `online_test_answer`'s
snapshotting in Phase 5.

**Approving a `LeaveRequest` reuses `staff_attendances.status = 'on_leave'`
— an enum value Phase 4 already shipped but nothing wrote automatically
until now.** `LeaveService::review()` calls a new
`AttendanceService::markOnLeave()` once per day in the approved range,
which reuses `AttendanceService`'s own private, `whereDate()`-safe
upsert helper — an already-marked day (e.g. approved after the fact) is
corrected, not duplicated, exactly like every other attendance write in
this codebase. No new attendance status was invented for this; it was
sitting unused since Phase 4 (`AttendanceStatus::OnLeave`, already
zero-weighted in `presentWeight()`).

**Bulk payroll generation is one transaction per staff member, not one
around the whole batch — the Phase 8 bulk-invoice-generation lesson
applied from the start this time, not rediscovered.** See
`PayrollService::generateForMonth()` and the equivalent note under Phase
8's key modeling decisions above for the underlying "unable to open
database file" issue this specific shape avoids under `php artisan serve`
on Windows.

**`StudentTransportAssignment` and `HostelAllocation` reuse
`SalaryStructure`'s "close, don't delete" shape.** Assigning a student to
a new route, or allocating them to a new hostel room, flips the previous
active row's flag (`is_active = false` / `status = vacated`) rather than
deleting it, for the same reason: a past record should stay resolvable,
not vanish. `HostelService::allocate()` additionally enforces room
capacity (`HostelRoom::occupiedCount()` — a live count of `allocated`
rows for that room — against `capacity`) before creating the new
allocation, all inside one transaction so the capacity check and the
supersede-plus-create can't race.

**Exactly one of `BookIssue.student_id`/`user_id` is enforced in
validation, not the database.** A book can be issued to a student or a
staff member but never both and never neither. `required_without` +
`prohibits` on both columns in `IssueBookRequest` express that XOR;
a `CHECK` constraint would have been the DB-native choice but isn't
portable identically across this project's SQLite (tests/dev) and MySQL
(production) targets, so — consistent with every other "pick one of two
nullable FKs" case in this codebase — it stays at the validation layer.

**`Book.available_copies` is a denormalized counter, shifted by every
write that touches copy count.** Issuing decrements it, returning
increments it, and editing `total_copies` shifts it by the delta (clamped
at 0, since copies currently on loan must stay accounted for even if
`total_copies` shrinks below that count) — `LibraryService`/`BookController`
are the only things allowed to touch it directly.

**Ten new entities across four sub-modules share one `view`/`manage`
permission shape — `library`, `transport`, `hostel`, `front-desk`.**
Unlike every prior module (which needed `view`/`create`/`edit`/`delete`,
or split further like Phase 8's `invoices` with `record-payment`/`void`/
etc.), none of Library/Transport/Hostel/Front Desk's actions needed
finer-grained control than "can see this module" vs. "can change
anything in it." That regularity is what motivated a new shared
`BaseViewManagePolicy` — see [rbac.md](rbac.md) for its shape and why it
generalizes the same two-permission pattern `GradingScalePolicy` already
used ad hoc since Phase 4-5.

**A `Certificate` snapshots its template's rendered body at issue time —
it never re-renders from a live template later.** Same rationale as a
`Payslip` snapshotting its `SalaryStructure`'s amounts: if a school edits
a certificate template's wording next year, every certificate already
issued from the old wording must stay exactly what was actually printed
and handed to that student, not silently reflow to the new text.
`CertificateService::issue()` does the placeholder substitution once,
at issue time, and writes the result to `certificates.content`.

**`Notice` and `Announcement` look similar but solve different problems,
and are deliberately two separate tables, not one with a `channel`
column.** A `Notice` is a passive bulletin board post — nobody's
notified, you have to go look at the board, and reading it needs no
permission at all (see [rbac.md](rbac.md)). An `Announcement` is an
active, audited broadcast — composing and sending is a permissioned
staff action that fans out to real recipients across up to four channels
and leaves a `sent_by`/`sent_at`/`recipient_count` record of exactly what
went out and to how many people. Collapsing them into one model would
have meant either gating notice-board reading behind a permission it
doesn't need, or letting a passive bulletin post masquerade as an
audited send. `AppNotification.announcement_id` is nullable specifically
so a future system-generated notification (e.g. "your leave request was
approved") can reuse the same inbox table without needing a fake
`Announcement` row behind it.

**Communication channels are stored as a plain `json` array
(`announcements.channels`), not an enum or a set of boolean columns.**
`['in_app', 'email']` is both the validated input shape
(`StoreAnnouncementRequest`) and the exact shape `AnnouncementService`
iterates to decide what to dispatch — no translation layer between "what
the sender picked" and "what actually got sent." A boolean-per-channel
schema would need a migration every time a new channel is added; this
doesn't.

**Phase 12 (Reports & Analytics, global search) added zero new tables —
every report and every search result is computed live from existing
tables, nothing is snapshotted or cached.** Same reasoning as Phase 11's
ID cards: a report's whole value is that it reflects *current* state: an
attendance percentage, an exam average, an occupancy rate. A cached
"reports" table would just be a staleness bug waiting to happen, with no
natural invalidation trigger (a report doesn't have a single write path
the way a `Payslip`/`Certificate` snapshot does — dozens of different
writes across a dozen modules could change what a report shows). If a
future phase needs pre-aggregated reporting for genuine performance
reasons at scale, that's a deliberate caching layer to design then, not
something to guess at now.

## Factories & seeders

`database/factories/*Factory.php` exist for every model, used by both tests
and the demo seeders. Seed order (`database/seeders/DatabaseSeeder.php`):

```
PermissionSeeder → PlanSeeder → SchoolSeeder → RolePermissionSeeder →
SuperAdminUserSeeder → SettingSeeder → AcademicStructureDemoSeeder →
DemoStudentSeeder
```

- **`PermissionSeeder`** — reads `config/permissions.php`, `firstOrCreate`s
  every `module.action` permission (idempotent, safe to re-run).
- **`PlanSeeder`** — placeholder Starter/Growth/Scale tiers (real pricing
  TBD — see [roadmap.md § Phase 6](roadmap.md#status)).
- **`SchoolSeeder`** — creates "Riverside Demo School" (`short_name: demo`),
  assigned the seeded Growth plan with `billing_status: active`.
- **`RolePermissionSeeder`** — creates Super Admin at team `0` with every
  permission, then delegates the 12 school-scoped roles + their permission
  matrix (see [rbac.md](rbac.md)) to
  `App\Services\SchoolProvisioningService::seedDefaultRoles()` for every
  existing school — the same method the self-service signup flow
  (Phase 6, Sub-phase C) calls to provision a brand-new tenant at runtime.
- **`SuperAdminUserSeeder`** — `superadmin@example.com` / `password`.
- **`SettingSeeder`** — global defaults (branding, localization, academic
  labels, admission number format, notification toggles) plus one
  school-specific override on the demo school (`branding.primary_color`)
  to prove per-school overrides actually take precedence over globals.
- **`AcademicStructureDemoSeeder`** — a full academic year, terms, grade
  levels, sections, subjects, rooms, and a class-teacher assignment for the
  demo school, plus `admin@riverside-demo.test` / `password` (School Admin).
- **`DemoStudentSeeder`** — a handful of demo students with guardians
  attached, including "Sam Student" used in manual smoke testing.

Run all of them with `php artisan migrate:fresh --seed`.
