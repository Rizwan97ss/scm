<?php

namespace Database\Seeders;

use App\Enums\Audience;
use App\Enums\BookIssueStatus;
use App\Enums\DiscountType;
use App\Enums\HomeworkSubmissionStatus;
use App\Enums\HostelAllocationStatus;
use App\Enums\HostelType;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayslipStatus;
use App\Enums\RemarkCategory;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ClassSubjectTeacher;
use App\Models\CreditNote;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\GradeBand;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\Holiday;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Room;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\SalaryStructure;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\StudentRemark;
use App\Models\StudentTransportAssignment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Services\AnnouncementService;
use App\Services\AttendanceService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Populates one tenant's database with a full, realistic spread of demo data
 * across every module -- meant to be run inside `$school->run(fn () => ...)`
 * (see SchoolProvisioningService::provision()), never against the landlord
 * connection. Unlike the older AcademicStructureDemoSeeder/DemoStudentSeeder
 * (both pre-tenancy-conversion and left as known-broken placeholders), this
 * never passes 'school_id' -- tenant tables don't have that column anymore
 * (Sub-phase D stripped it; only stale #[Fillable] metadata still lists it
 * on some models, which is dead weight, not a real column).
 */
class TenantDemoDataSeeder extends Seeder
{
    private School $school;

    private AcademicYear $year;

    /** @var array<string, Term> */
    private array $terms = [];

    /** @var array<int, GradeLevel> keyed by sequence */
    private array $gradeLevels = [];

    /** @var array<string, Room> */
    private array $rooms = [];

    /** @var array<string, Section> keyed by "{sequence}-{A|B}" */
    private array $sections = [];

    /** @var array<string, Subject> */
    private array $subjects = [];

    /** @var array<string, User> keyed by a short handle */
    private array $staff = [];

    /** @var array<int, User> flat list of teacher-capable staff for random assignment */
    private array $teachers = [];

    /** @var array<int, Student> */
    private array $students = [];

    public function run(School $school): void
    {
        $this->school = $school;

        $this->command?->info("Seeding demo data for {$school->name} ({$school->slug})...");

        $this->academicStructure();
        $this->staffUsers();
        $this->classAssignments();
        $this->students();
        $this->gradingAndExams();
        $this->questionBank();
        $this->homework();
        $this->attendance();
        $this->fees();
        $this->library();
        $this->transport();
        $this->hostel();
        $this->hr();
        $this->certificates();
        $this->remarks();
        $this->visitors();
        $this->notices();

        $this->command?->info("Done seeding {$school->name}.");
    }

    private function academicStructure(): void
    {
        $this->year = AcademicYear::query()->create([
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);

        $termDefs = [
            ['name' => 'Term 1', 'start_date' => '2025-09-01', 'end_date' => '2026-01-16', 'sequence' => 1, 'is_current' => false],
            ['name' => 'Term 2', 'start_date' => '2026-01-19', 'end_date' => '2026-06-30', 'sequence' => 2, 'is_current' => true],
        ];
        foreach ($termDefs as $def) {
            $this->terms[$def['name']] = Term::query()->create(['academic_year_id' => $this->year->id, ...$def]);
        }

        $departments = [
            ['code' => 'MATSCI', 'name' => 'Mathematics & Science'],
            ['code' => 'HUMLANG', 'name' => 'Languages & Humanities'],
            ['code' => 'ARTPE', 'name' => 'Arts & Physical Education'],
        ];
        $departmentModels = [];
        foreach ($departments as $d) {
            $departmentModels[$d['code']] = Department::query()->create($d);
        }

        $levels = ['Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5'];
        foreach ($levels as $sequence => $name) {
            $code = $sequence === 0 ? 'K' : "G{$sequence}";
            $this->gradeLevels[$sequence] = GradeLevel::query()->create(['code' => $code, 'name' => $name, 'sequence' => $sequence]);
        }

        $roomDefs = [
            ['code' => 'R101', 'name' => 'Room 101', 'capacity' => 30, 'type' => 'classroom'],
            ['code' => 'R102', 'name' => 'Room 102', 'capacity' => 30, 'type' => 'classroom'],
            ['code' => 'R103', 'name' => 'Room 103', 'capacity' => 30, 'type' => 'classroom'],
            ['code' => 'R104', 'name' => 'Room 104', 'capacity' => 30, 'type' => 'classroom'],
            ['code' => 'R105', 'name' => 'Room 105', 'capacity' => 28, 'type' => 'classroom'],
            ['code' => 'R106', 'name' => 'Room 106', 'capacity' => 28, 'type' => 'classroom'],
            ['code' => 'SCILAB', 'name' => 'Science Lab', 'capacity' => 24, 'type' => 'lab'],
            ['code' => 'ARTST', 'name' => 'Art Studio', 'capacity' => 24, 'type' => 'lab'],
            ['code' => 'GYM', 'name' => 'Gymnasium', 'capacity' => 60, 'type' => 'hall'],
            ['code' => 'HALL', 'name' => 'Assembly Hall', 'capacity' => 200, 'type' => 'hall'],
        ];
        foreach ($roomDefs as $r) {
            $this->rooms[$r['code']] = Room::query()->create($r);
        }

        $roomCodes = array_values(array_filter(array_keys($this->rooms), fn ($c) => str_starts_with($c, 'R')));
        $roomIndex = 0;
        foreach ($this->gradeLevels as $sequence => $gradeLevel) {
            foreach (['A', 'B'] as $letter) {
                $room = $this->rooms[$roomCodes[$roomIndex % count($roomCodes)]];
                $roomIndex++;
                $this->sections["{$sequence}-{$letter}"] = Section::query()->create([
                    'academic_year_id' => $this->year->id,
                    'grade_level_id' => $gradeLevel->id,
                    'name' => $letter,
                    'capacity' => 25,
                    'room_id' => $room->id,
                ]);
            }
        }

        $subjectDefs = [
            ['code' => 'MATH', 'name' => 'Mathematics', 'department' => 'MATSCI'],
            ['code' => 'SCI', 'name' => 'Science', 'department' => 'MATSCI'],
            ['code' => 'ENG', 'name' => 'English Language', 'department' => 'HUMLANG'],
            ['code' => 'SOC', 'name' => 'Social Studies', 'department' => 'HUMLANG'],
            ['code' => 'ART', 'name' => 'Art', 'department' => 'ARTPE', 'is_elective' => true],
            ['code' => 'PE', 'name' => 'Physical Education', 'department' => 'ARTPE'],
        ];
        foreach ($subjectDefs as $s) {
            $this->subjects[$s['code']] = Subject::query()->create([
                'code' => $s['code'],
                'name' => $s['name'],
                'department_id' => $departmentModels[$s['department']]->id,
                'is_elective' => $s['is_elective'] ?? false,
            ]);
        }

        $periods = [
            ['name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'sequence' => 1, 'is_break' => false],
            ['name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'sequence' => 2, 'is_break' => false],
            ['name' => 'Break', 'start_time' => '09:30', 'end_time' => '09:45', 'sequence' => 3, 'is_break' => true],
            ['name' => 'Period 3', 'start_time' => '09:45', 'end_time' => '10:30', 'sequence' => 4, 'is_break' => false],
            ['name' => 'Period 4', 'start_time' => '10:30', 'end_time' => '11:15', 'sequence' => 5, 'is_break' => false],
            ['name' => 'Lunch', 'start_time' => '11:15', 'end_time' => '12:00', 'sequence' => 6, 'is_break' => true],
            ['name' => 'Period 5', 'start_time' => '12:00', 'end_time' => '12:45', 'sequence' => 7, 'is_break' => false],
        ];
        foreach ($periods as $p) {
            TimetablePeriod::query()->create($p);
        }

        Holiday::query()->create(['academic_year_id' => $this->year->id, 'name' => 'Winter Break', 'start_date' => '2025-12-22', 'end_date' => '2026-01-02', 'type' => 'school_specific']);
        Holiday::query()->create(['academic_year_id' => $this->year->id, 'name' => 'Spring Break', 'start_date' => '2026-03-16', 'end_date' => '2026-03-20', 'type' => 'school_specific']);
    }

    private function staffUsers(): void
    {
        $designationDefs = ['Teacher', 'Senior Teacher', 'Vice Principal', 'Principal', 'Accountant', 'Administrative Staff', 'Librarian', 'Support Staff'];
        $designations = [];
        foreach ($designationDefs as $name) {
            $designations[$name] = Designation::query()->create(['name' => $name, 'description' => "{$name} at {$this->school->name}", 'is_active' => true]);
        }

        // The School Admin already exists -- SchoolProvisioningService::provision()
        // creates it before this seeder ever runs (it's the account the signup
        // handoff token is issued for). Reuse it rather than creating a second,
        // duplicate-email admin.
        $existingAdmin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'School Admin'))->first();
        if ($existingAdmin) {
            $existingAdmin->update([
                'designation_id' => $designations['Administrative Staff']->id,
                'employee_id' => 'EMP-1000',
                'hire_date' => fake()->dateTimeBetween('-5 years', '-3 months')->format('Y-m-d'),
                'phone' => fake()->numerify('+1-555-01##'),
            ]);
            $this->staff['admin'] = $existingAdmin;
        }

        $roster = [
            'principal' => ['role' => 'Principal', 'designation' => 'Principal'],
            'vice_principal' => ['role' => 'Management', 'designation' => 'Vice Principal'],
            'teacher_math' => ['role' => 'Class Teacher', 'designation' => 'Senior Teacher'],
            'teacher_english' => ['role' => 'Class Teacher', 'designation' => 'Senior Teacher'],
            'teacher_science' => ['role' => 'Teacher', 'designation' => 'Teacher'],
            'teacher_social' => ['role' => 'Teacher', 'designation' => 'Teacher'],
            'teacher_art' => ['role' => 'Teacher', 'designation' => 'Teacher'],
            'accountant' => ['role' => 'Accountant', 'designation' => 'Accountant'],
            'hr' => ['role' => 'HR Staff', 'designation' => 'Administrative Staff'],
            'receptionist' => ['role' => 'Receptionist', 'designation' => 'Support Staff'],
            'librarian' => ['role' => 'Librarian', 'designation' => 'Librarian'],
            'transport' => ['role' => 'Transport Staff', 'designation' => 'Support Staff'],
        ];

        $employeeSeq = 1001;
        foreach ($roster as $handle => $def) {
            $first = fake()->firstName();
            $last = fake()->lastName();
            $email = Str::slug("{$first}.{$last}").'@'.$this->school->slug.'.test';

            $user = User::query()->create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password' => Hash::make('password123'),
                'status' => 'active',
                'email_verified_at' => now(),
                'designation_id' => $designations[$def['designation']]->id,
                'employee_id' => 'EMP-'.($employeeSeq++),
                'hire_date' => fake()->dateTimeBetween('-5 years', '-3 months')->format('Y-m-d'),
                'phone' => fake()->numerify('+1-555-01##'),
            ]);
            $user->assignRole($def['role']);

            $this->staff[$handle] = $user;
            if (in_array($def['role'], ['Teacher', 'Class Teacher'], true)) {
                $this->teachers[] = $user;
            }
        }

        $this->command?->info("  Staff seeded (password: \"password123\"): admin@{$this->school->slug}.test");
    }

    private function classAssignments(): void
    {
        $this->sections['1-A']->update(['class_teacher_id' => $this->staff['teacher_math']->id]);
        $this->sections['2-A']->update(['class_teacher_id' => $this->staff['teacher_english']->id]);
        $this->sections['3-A']->update(['class_teacher_id' => $this->staff['teacher_math']->id]);

        $subjectTeacherMap = [
            'MATH' => $this->staff['teacher_math'],
            'SCI' => $this->staff['teacher_science'],
            'ENG' => $this->staff['teacher_english'],
            'SOC' => $this->staff['teacher_social'],
            'ART' => $this->staff['teacher_art'],
            'PE' => $this->staff['teacher_science'],
        ];

        $periods = TimetablePeriod::query()->where('is_break', false)->orderBy('sequence')->get();
        $dayCycle = [1, 2, 3, 4, 5];

        foreach ($this->sections as $section) {
            // Local per-section slot counter: period = slot % periods-per-day,
            // day = slot / periods-per-day. With 6 subjects and only 5
            // non-break periods, subject #6 spills onto a second day at
            // period 0 rather than wrapping onto the SAME (period, day) an
            // earlier subject already used -- the timetable_entries table
            // has a unique constraint on (section_id, timetable_period_id,
            // day_of_week), so a naive `subjectIndex % periods->count()`
            // that ignores the day component collides once subjects
            // outnumber periods.
            $slot = 0;
            foreach ($this->subjects as $code => $subject) {
                $teacher = $subjectTeacherMap[$code];

                ClassSubjectTeacher::query()->create([
                    'academic_year_id' => $this->year->id,
                    'section_id' => $section->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                ]);

                $period = $periods[$slot % $periods->count()];
                $day = $dayCycle[intdiv($slot, $periods->count()) % count($dayCycle)];

                TimetableEntry::query()->create([
                    'academic_year_id' => $this->year->id,
                    'section_id' => $section->id,
                    'timetable_period_id' => $period->id,
                    'day_of_week' => $day,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                    'room_id' => $section->room_id,
                ]);

                $slot++;
            }
        }
    }

    private function students(): void
    {
        $idGenerator = app(StudentIdGeneratorService::class);
        $enrollment = app(StudentEnrollmentService::class);
        $performedBy = $this->staff['admin'];
        $admissionDate = now()->subMonths(3);

        $guardianPool = [];
        for ($i = 0; $i < 20; $i++) {
            $guardianPool[] = Guardian::query()->create([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->numerify('+1-555-02##'),
                'invited_at' => fake()->boolean(60) ? now() : null,
            ]);
        }
        $guardianIndex = 0;

        foreach ($this->sections as $section) {
            for ($i = 0; $i < 4; $i++) {
                $gender = fake()->randomElement(['male', 'female']);
                $first = fake()->firstName($gender === 'male' ? 'male' : 'female');
                $last = fake()->lastName();

                $student = Student::query()->create([
                    'admission_number' => $idGenerator->generate($this->school, $admissionDate),
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'date_of_birth' => fake()->dateTimeBetween('-11 years', '-4 years')->format('Y-m-d'),
                    'current_grade_level_id' => $section->grade_level_id,
                    'current_section_id' => $section->id,
                    'academic_year_id' => $this->year->id,
                    'admission_date' => $admissionDate->toDateString(),
                    'status' => 'active',
                    'emergency_contact_name' => fake()->name(),
                    'emergency_contact_phone' => fake()->numerify('+1-555-03##'),
                    'address_line1' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'country' => 'United States',
                ]);

                $enrollment->recordAdmission($student, $performedBy, $admissionDate);

                $primaryGuardian = $guardianPool[$guardianIndex % count($guardianPool)];
                $guardianIndex++;
                $student->guardians()->attach($primaryGuardian->id, [
                    'relationship_type' => fake()->randomElement(['father', 'mother', 'guardian']),
                    'is_primary' => true,
                    'can_pickup' => true,
                ]);

                $this->students[] = $student;
            }
        }

        $this->command?->info('  Students seeded: '.count($this->students));
    }

    private function gradingAndExams(): void
    {
        $scale = GradingScale::query()->create(['name' => 'Standard Scale', 'is_default' => true]);
        $bands = [
            ['min_percentage' => 90, 'max_percentage' => 100, 'grade_label' => 'A', 'grade_point' => 4.0, 'remark' => 'Excellent'],
            ['min_percentage' => 80, 'max_percentage' => 89.99, 'grade_label' => 'B', 'grade_point' => 3.0, 'remark' => 'Very Good'],
            ['min_percentage' => 70, 'max_percentage' => 79.99, 'grade_label' => 'C', 'grade_point' => 2.0, 'remark' => 'Good'],
            ['min_percentage' => 60, 'max_percentage' => 69.99, 'grade_label' => 'D', 'grade_point' => 1.0, 'remark' => 'Satisfactory'],
            ['min_percentage' => 0, 'max_percentage' => 59.99, 'grade_label' => 'F', 'grade_point' => 0.0, 'remark' => 'Needs Improvement'],
        ];
        foreach ($bands as $b) {
            GradeBand::query()->create(['grading_scale_id' => $scale->id, ...$b]);
        }

        $examDefs = [
            ['name' => 'Midterm Exam', 'term' => 'Term 1', 'weight' => 40, 'date' => '2025-11-10'],
            ['name' => 'Final Exam', 'term' => 'Term 2', 'weight' => 60, 'date' => '2026-05-18'],
        ];

        $enteredBy = $this->staff['teacher_math'];

        foreach ($examDefs as $def) {
            $exam = Exam::query()->create([
                'academic_year_id' => $this->year->id,
                'term_id' => $this->terms[$def['term']]->id,
                'name' => $def['name'],
                'weight' => $def['weight'],
                'is_published' => true,
                'published_at' => now(),
            ]);

            foreach (['1-A', '2-A', '3-A'] as $sectionKey) {
                $section = $this->sections[$sectionKey];
                foreach (['MATH', 'SCI', 'ENG'] as $subjectCode) {
                    $examSubject = ExamSubject::query()->create([
                        'exam_id' => $exam->id,
                        'subject_id' => $this->subjects[$subjectCode]->id,
                        'section_id' => $section->id,
                        'grading_scale_id' => $scale->id,
                        'max_marks' => 100,
                        'passing_marks' => 35,
                        'exam_date' => $def['date'],
                        'duration_minutes' => 90,
                    ]);

                    $studentsInSection = array_values(array_filter($this->students, fn (Student $s) => $s->current_section_id === $section->id));
                    foreach ($studentsInSection as $student) {
                        ExamMark::query()->create([
                            'exam_subject_id' => $examSubject->id,
                            'student_id' => $student->id,
                            'marks_obtained' => fake()->numberBetween(35, 100),
                            'is_absent' => false,
                            'entered_by' => $enteredBy->id,
                        ]);
                    }
                }
            }
        }
    }

    private function questionBank(): void
    {
        $mathTeacher = $this->staff['teacher_math'];
        $questions = [
            ['text' => 'What is 7 + 8?', 'type' => 'mcq', 'options' => ['13', '14', '15', '16'], 'correct' => 2],
            ['text' => 'What is 12 x 3?', 'type' => 'mcq', 'options' => ['24', '36', '30', '32'], 'correct' => 1],
            ['text' => 'The sun rises in the east.', 'type' => 'true_false', 'options' => ['True', 'False'], 'correct' => 0],
            ['text' => 'Water boils at 100°C at sea level.', 'type' => 'true_false', 'options' => ['True', 'False'], 'correct' => 0],
        ];

        foreach ($questions as $q) {
            $question = Question::query()->create([
                'subject_id' => $this->subjects['MATH']->id,
                'type' => $q['type'],
                'text' => $q['text'],
                'default_marks' => 1,
                'created_by' => $mathTeacher->id,
            ]);

            foreach ($q['options'] as $index => $optionText) {
                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $index === $q['correct'],
                    'sequence' => $index + 1,
                ]);
            }
        }
    }

    private function homework(): void
    {
        $sections = ['1-A', '2-A', '3-A'];
        $subjectCodes = ['MATH', 'ENG'];

        foreach ($sections as $sectionKey) {
            $section = $this->sections[$sectionKey];
            foreach ($subjectCodes as $subjectCode) {
                $teacher = $subjectCode === 'MATH' ? $this->staff['teacher_math'] : $this->staff['teacher_english'];

                $homework = Homework::query()->create([
                    'academic_year_id' => $this->year->id,
                    'section_id' => $section->id,
                    'subject_id' => $this->subjects[$subjectCode]->id,
                    'teacher_id' => $teacher->id,
                    'title' => "{$this->subjects[$subjectCode]->name} — ".fake()->sentence(4),
                    'description' => fake()->paragraph(),
                    'due_date' => now()->addDays(fake()->numberBetween(2, 10))->toDateString(),
                    'max_score' => 20,
                ]);

                $studentsInSection = array_values(array_filter($this->students, fn (Student $s) => $s->current_section_id === $section->id));
                foreach (array_slice($studentsInSection, 0, 3) as $student) {
                    $graded = fake()->boolean(60);
                    HomeworkSubmission::query()->create([
                        'homework_id' => $homework->id,
                        'student_id' => $student->id,
                        'status' => $graded ? HomeworkSubmissionStatus::Graded->value : HomeworkSubmissionStatus::Submitted->value,
                        'content' => fake()->paragraph(),
                        'submitted_at' => now()->subDays(fake()->numberBetween(1, 5)),
                        'score' => $graded ? fake()->numberBetween(10, 20) : null,
                        'feedback' => $graded ? fake()->sentence() : null,
                        'graded_at' => $graded ? now()->subDay() : null,
                        'graded_by' => $graded ? $teacher->id : null,
                    ]);
                }
            }
        }
    }

    private function attendance(): void
    {
        $attendanceService = app(AttendanceService::class);
        $marker = $this->staff['admin'];

        $schoolDays = [];
        $cursor = now()->subDays(14);
        while (count($schoolDays) < 8) {
            if (! $cursor->isWeekend()) {
                $schoolDays[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        foreach ($schoolDays as $date) {
            foreach ($this->sections as $section) {
                $studentsInSection = array_values(array_filter($this->students, fn (Student $s) => $s->current_section_id === $section->id));
                if ($studentsInSection === []) {
                    continue;
                }

                $entries = array_map(fn (Student $s) => [
                    'student_id' => $s->id,
                    'status' => fake()->randomElement(['present', 'present', 'present', 'present', 'late', 'absent']),
                ], $studentsInSection);

                $attendanceService->markStudentsBulk($section, $date, null, $entries, $marker);
            }

            $staffEntries = array_map(fn (User $u) => [
                'user_id' => $u->id,
                'status' => fake()->randomElement(['present', 'present', 'present', 'present', 'late']),
            ], array_values($this->staff));

            $attendanceService->markStaffBulk($staffEntries, $date, $marker);
        }
    }

    private function fees(): void
    {
        $categoryNames = ['Tuition', 'Transport', 'Library', 'Laboratory', 'Examination'];
        $categories = [];
        foreach ($categoryNames as $name) {
            $categories[$name] = FeeCategory::query()->create(['name' => $name, 'description' => "{$name} fee", 'is_active' => true]);
        }

        $structures = [];
        foreach ($categories as $name => $category) {
            $structures[$name] = FeeStructure::query()->create([
                'academic_year_id' => $this->year->id,
                'fee_category_id' => $category->id,
                'name' => "{$name} — 2025-2026",
                'amount' => match ($name) {
                    'Tuition' => 3500,
                    'Transport' => 600,
                    'Library' => 100,
                    'Laboratory' => 150,
                    'Examination' => 200,
                },
                'frequency' => $name === 'Tuition' ? 'annual' : 'annual',
                'is_active' => true,
            ]);
        }

        $creator = $this->staff['accountant'];
        $invoiceSeq = 1;

        foreach (array_slice($this->students, 0, 30) as $student) {
            if (fake()->boolean(30)) {
                StudentFeeAssignment::query()->create([
                    'student_id' => $student->id,
                    'fee_structure_id' => $structures['Tuition']->id,
                    'discount_type' => DiscountType::Percentage->value,
                    'discount_value' => 10,
                    'reason' => 'Sibling discount',
                ]);
            }

            $items = [
                ['category' => 'Tuition', 'amount' => 3500],
                ['category' => 'Examination', 'amount' => 200],
            ];
            $subtotal = array_sum(array_column($items, 'amount'));
            $discountTotal = fake()->boolean(30) ? round($subtotal * 0.1, 2) : 0;
            $total = $subtotal - $discountTotal;

            $invoiceNumber = 'INV-2026-'.str_pad((string) $invoiceSeq++, 5, '0', STR_PAD_LEFT);

            $invoice = Invoice::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $this->year->id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => now()->subDays(30)->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'status' => InvoiceStatus::Issued->value,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'amount_paid' => 0,
                'credit_total' => 0,
                'created_by' => $creator->id,
            ]);

            foreach ($items as $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'fee_category_id' => $categories[$item['category']]->id,
                    'description' => "{$item['category']} fee",
                    'quantity' => 1,
                    'unit_amount' => $item['amount'],
                    'amount' => $item['amount'],
                ]);
            }

            $paymentOutcome = fake()->randomElement(['unpaid', 'unpaid', 'partial', 'paid', 'paid']);
            $amountPaid = match ($paymentOutcome) {
                'unpaid' => 0,
                'partial' => round($total * 0.5, 2),
                'paid' => $total,
            };

            if ($amountPaid > 0) {
                Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'payment_number' => 'PAY-2026-'.str_pad((string) $invoiceSeq, 5, '0', STR_PAD_LEFT),
                    'amount' => $amountPaid,
                    'method' => fake()->randomElement(PaymentMethod::cases())->value,
                    'gateway' => 'manual',
                    'paid_at' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                    'received_by' => $creator->id,
                ]);
            }

            $invoice->update([
                'amount_paid' => $amountPaid,
                'status' => match (true) {
                    $amountPaid >= $total => InvoiceStatus::Paid->value,
                    $amountPaid > 0 => InvoiceStatus::PartiallyPaid->value,
                    default => InvoiceStatus::Issued->value,
                },
            ]);

            if ($paymentOutcome === 'paid' && fake()->boolean(15)) {
                $creditAmount = round($total * 0.05, 2);
                CreditNote::query()->create([
                    'invoice_id' => $invoice->id,
                    'credit_note_number' => 'CN-2026-'.str_pad((string) $invoiceSeq, 5, '0', STR_PAD_LEFT),
                    'amount' => $creditAmount,
                    'reason' => 'Goodwill adjustment',
                    'issued_by' => $creator->id,
                    'issued_at' => now()->subDays(2),
                ]);
                $invoice->update(['credit_total' => $creditAmount]);
            }
        }
    }

    private function library(): void
    {
        $titles = [
            ['title' => 'Charlotte\'s Web', 'author' => 'E.B. White', 'category' => 'Fiction'],
            ['title' => 'The Magic School Bus', 'author' => 'Joanna Cole', 'category' => 'Science'],
            ['title' => 'Matilda', 'author' => 'Roald Dahl', 'category' => 'Fiction'],
            ['title' => 'A Brief History of Time', 'author' => 'Stephen Hawking', 'category' => 'Science'],
            ['title' => 'Percy Jackson: The Lightning Thief', 'author' => 'Rick Riordan', 'category' => 'Fiction'],
            ['title' => 'World Atlas for Kids', 'author' => 'National Geographic', 'category' => 'Reference'],
            ['title' => 'The Wonderful Wizard of Oz', 'author' => 'L. Frank Baum', 'category' => 'Fiction'],
            ['title' => 'Basic Mathematics', 'author' => 'Serge Lang', 'category' => 'Mathematics'],
            ['title' => 'Charlie and the Chocolate Factory', 'author' => 'Roald Dahl', 'category' => 'Fiction'],
            ['title' => 'Encyclopedia of Animals', 'author' => 'DK Publishing', 'category' => 'Reference'],
        ];

        $books = [];
        foreach ($titles as $t) {
            $books[] = Book::query()->create([
                'title' => $t['title'],
                'author' => $t['author'],
                'isbn' => fake()->unique()->isbn13(),
                'category' => $t['category'],
                'total_copies' => 3,
                'available_copies' => 3,
                'is_active' => true,
            ]);
        }

        $librarian = $this->staff['librarian'];
        $issueSeq = 1;
        foreach (array_slice($this->students, 0, 15) as $index => $student) {
            $book = $books[$index % count($books)];
            $status = fake()->randomElement([BookIssueStatus::Issued, BookIssueStatus::Returned, BookIssueStatus::Returned]);

            BookIssue::query()->create([
                'book_id' => $book->id,
                'student_id' => $student->id,
                'issue_date' => now()->subDays(fake()->numberBetween(3, 20))->toDateString(),
                'due_date' => now()->addDays(fake()->numberBetween(-3, 10))->toDateString(),
                'return_date' => $status === BookIssueStatus::Returned ? now()->subDays(fake()->numberBetween(1, 5))->toDateString() : null,
                'fine_amount' => 0,
                'status' => $status->value,
                'issued_by' => $librarian->id,
            ]);

            if ($status === BookIssueStatus::Issued) {
                $book->decrement('available_copies');
            }
            $issueSeq++;
        }
    }

    private function transport(): void
    {
        $vehicles = [];
        foreach (['Bus 1', 'Bus 2', 'Van 1'] as $i => $name) {
            $vehicles[] = Vehicle::query()->create([
                'registration_number' => 'SCH-'.fake()->numerify('####'),
                'capacity' => $i < 2 ? 40 : 15,
                'driver_name' => fake()->name(),
                'driver_phone' => fake()->numerify('+1-555-04##'),
                'is_active' => true,
            ]);
        }

        $routeDefs = [
            ['name' => 'North Route', 'stops' => ['Maple Ave', 'Oak Street', 'Pine Corner', 'School Gate']],
            ['name' => 'South Route', 'stops' => ['Elm Street', 'Cedar Lane', 'Birch Road', 'School Gate']],
        ];

        $assignmentPool = [];
        foreach ($routeDefs as $i => $def) {
            $route = Route::query()->create(['name' => $def['name'], 'description' => "Serves the {$def['name']} area", 'is_active' => true]);
            $stops = [];
            foreach ($def['stops'] as $sequence => $stopName) {
                $stops[] = RouteStop::query()->create(['route_id' => $route->id, 'name' => $stopName, 'sequence' => $sequence + 1]);
            }
            $assignmentPool[] = ['route' => $route, 'stops' => $stops, 'vehicle' => $vehicles[$i]];
        }

        foreach (array_slice($this->students, 0, 15) as $index => $student) {
            $pool = $assignmentPool[$index % count($assignmentPool)];
            StudentTransportAssignment::query()->create([
                'student_id' => $student->id,
                'route_id' => $pool['route']->id,
                'route_stop_id' => $pool['stops'][array_rand($pool['stops'])]->id,
                'vehicle_id' => $pool['vehicle']->id,
                'effective_from' => now()->subMonths(2)->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    private function hostel(): void
    {
        $hostelDefs = [
            ['name' => 'Sunrise Hostel', 'type' => HostelType::Boys],
            ['name' => 'Moonlight Hostel', 'type' => HostelType::Girls],
        ];

        $allocatable = [];
        foreach ($hostelDefs as $def) {
            $hostel = Hostel::query()->create([
                'name' => $def['name'],
                'type' => $def['type']->value,
                'address' => fake()->streetAddress(),
                'warden_name' => fake()->name(),
                'warden_phone' => fake()->numerify('+1-555-05##'),
                'is_active' => true,
            ]);

            for ($i = 1; $i <= 4; $i++) {
                $room = HostelRoom::query()->create([
                    'hostel_id' => $hostel->id,
                    'room_number' => "R{$i}",
                    'capacity' => 2,
                    'is_active' => true,
                ]);
                $allocatable[] = ['room' => $room, 'gender' => $def['type'] === HostelType::Boys ? 'male' : 'female'];
            }
        }

        $bedNumber = 1;
        foreach ($this->students as $student) {
            if (! fake()->boolean(15)) {
                continue;
            }

            $candidates = array_values(array_filter($allocatable, fn ($a) => $a['gender'] === $student->gender->value));
            if ($candidates === []) {
                continue;
            }

            $slot = $candidates[array_rand($candidates)];
            $room = $slot['room'];
            $occupied = HostelAllocation::query()->where('hostel_room_id', $room->id)->where('status', HostelAllocationStatus::Allocated->value)->count();
            if ($occupied >= $room->capacity) {
                continue;
            }

            HostelAllocation::query()->create([
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'bed_number' => (string) ($bedNumber++),
                'allocated_date' => now()->subMonths(2)->toDateString(),
                'status' => HostelAllocationStatus::Allocated->value,
            ]);
        }
    }

    private function hr(): void
    {
        $salaryDefs = [
            'admin' => 4500, 'principal' => 6000, 'vice_principal' => 5200,
            'teacher_math' => 3800, 'teacher_english' => 3800, 'teacher_science' => 3600,
            'teacher_social' => 3400, 'teacher_art' => 3200, 'accountant' => 3600,
            'hr' => 3400, 'receptionist' => 2600, 'librarian' => 2800, 'transport' => 2600,
        ];

        $structures = [];
        foreach ($salaryDefs as $handle => $basic) {
            $allowances = round($basic * 0.15, 2);
            $deductions = round($basic * 0.05, 2);
            $structures[$handle] = SalaryStructure::query()->create([
                'user_id' => $this->staff[$handle]->id,
                'basic_salary' => $basic,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'effective_from' => now()->subYear()->toDateString(),
                'is_active' => true,
            ]);
        }

        $generator = $this->staff['hr'];
        foreach ([now()->subMonth(), now()] as $monthDate) {
            foreach ($structures as $handle => $structure) {
                $net = $structure->basic_salary + $structure->allowances - $structure->deductions;
                $isPaid = $monthDate->lt(now());
                Payslip::query()->create([
                    'user_id' => $this->staff[$handle]->id,
                    'salary_structure_id' => $structure->id,
                    'payslip_number' => 'PS-'.$monthDate->format('Ym').'-'.str_pad((string) (array_search($handle, array_keys($structures), true) + 1), 4, '0', STR_PAD_LEFT),
                    'month' => (int) $monthDate->format('m'),
                    'year' => (int) $monthDate->format('Y'),
                    'basic_salary' => $structure->basic_salary,
                    'allowances' => $structure->allowances,
                    'deductions' => $structure->deductions,
                    'net_salary' => $net,
                    'status' => $isPaid ? PayslipStatus::Paid->value : PayslipStatus::Generated->value,
                    'paid_at' => $isPaid ? $monthDate->copy()->endOfMonth() : null,
                    'generated_by' => $generator->id,
                ]);
            }
        }

        $leaveTypeDefs = [
            ['name' => 'Casual Leave', 'days_allowed_per_year' => 12, 'is_paid' => true],
            ['name' => 'Sick Leave', 'days_allowed_per_year' => 10, 'is_paid' => true],
            ['name' => 'Earned Leave', 'days_allowed_per_year' => 15, 'is_paid' => true],
            ['name' => 'Unpaid Leave', 'days_allowed_per_year' => 0, 'is_paid' => false],
        ];
        $leaveTypes = [];
        foreach ($leaveTypeDefs as $def) {
            $leaveTypes[$def['name']] = LeaveType::query()->create(['description' => "{$def['name']} policy", 'is_active' => true, ...$def]);
        }

        $reviewer = $this->staff['hr'];
        $applicants = ['teacher_science', 'teacher_social', 'receptionist', 'librarian', 'accountant'];
        $statuses = [LeaveStatus::Approved, LeaveStatus::Pending, LeaveStatus::Rejected, LeaveStatus::Approved, LeaveStatus::Pending];
        foreach ($applicants as $index => $handle) {
            $start = now()->addDays(fake()->numberBetween(-10, 20));
            $end = $start->copy()->addDays(fake()->numberBetween(0, 2));
            $status = $statuses[$index];

            LeaveRequest::query()->create([
                'user_id' => $this->staff[$handle]->id,
                'leave_type_id' => $leaveTypes[fake()->randomElement(['Casual Leave', 'Sick Leave', 'Earned Leave'])]->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $start->diffInDays($end) + 1,
                'reason' => fake()->sentence(),
                'status' => $status->value,
                'reviewed_by' => $status !== LeaveStatus::Pending ? $reviewer->id : null,
                'reviewed_at' => $status !== LeaveStatus::Pending ? now()->subDay() : null,
            ]);
        }
    }

    private function certificates(): void
    {
        $templates = [
            [
                'name' => 'Bonafide Certificate',
                'type' => 'Bonafide',
                'body' => 'This is to certify that {{student_name}} (Admission No: {{admission_number}}) is a bonafide student of {{school_name}}, currently studying in {{grade_level}} - {{section}}. Issued on {{date}}.',
            ],
            [
                'name' => 'Character Certificate',
                'type' => 'Character',
                'body' => 'This is to certify that {{student_name}} (Admission No: {{admission_number}}) of {{school_name}} has been of good moral character during their time at this institution. Issued on {{date}}.',
            ],
        ];

        $templateModels = [];
        foreach ($templates as $t) {
            $templateModels[] = CertificateTemplate::query()->create([...$t, 'is_active' => true]);
        }

        $issuer = $this->staff['admin'];
        $certSeq = 1;
        foreach (array_slice($this->students, 0, 6) as $student) {
            $template = $templateModels[array_rand($templateModels)];
            $gradeLevel = $this->findGradeLevel($student->current_grade_level_id);
            $section = $this->findSection($student->current_section_id);

            $content = str_replace(
                ['{{student_name}}', '{{admission_number}}', '{{grade_level}}', '{{section}}', '{{school_name}}', '{{date}}'],
                [$student->full_name, $student->admission_number, $gradeLevel?->name ?? '', $section?->name ?? '', $this->school->name, now()->toDateString()],
                $template->body
            );

            Certificate::query()->create([
                'student_id' => $student->id,
                'certificate_template_id' => $template->id,
                'certificate_number' => 'CERT-2026-'.str_pad((string) $certSeq++, 4, '0', STR_PAD_LEFT),
                'issued_date' => now()->subDays(fake()->numberBetween(1, 60))->toDateString(),
                'issued_by' => $issuer->id,
                'content' => $content,
            ]);
        }
    }

    private function remarks(): void
    {
        $categories = RemarkCategory::cases();
        foreach (array_slice($this->students, 0, 12) as $index => $student) {
            $section = $this->findSection($student->current_section_id);
            StudentRemark::query()->create([
                'student_id' => $student->id,
                'author_id' => $this->teachers[$index % count($this->teachers)]->id,
                'section_id' => $section?->id,
                'category' => $categories[$index % count($categories)]->value,
                'body' => fake()->sentence(12),
                'visible_to_guardian' => fake()->boolean(70),
            ]);
        }
    }

    private function visitors(): void
    {
        $purposes = ['Admission enquiry', 'Meeting with teacher', 'Fee payment', 'Delivery', 'Other'];
        $receptionist = $this->staff['receptionist'];
        for ($i = 0; $i < 6; $i++) {
            $checkIn = now()->subHours(fake()->numberBetween(1, 72));
            Visitor::query()->create([
                'name' => fake()->name(),
                'phone' => fake()->numerify('+1-555-06##'),
                'purpose' => $purposes[$i % count($purposes)],
                'whom_to_meet' => fake()->name(),
                'check_in_time' => $checkIn,
                'check_out_time' => fake()->boolean(70) ? $checkIn->copy()->addMinutes(fake()->numberBetween(10, 60)) : null,
                'logged_by' => $receptionist->id,
            ]);
        }
    }

    private function notices(): void
    {
        $creator = $this->staff['admin'];
        $noticeDefs = [
            ['title' => 'Parent-Teacher Meeting', 'type' => 'event', 'audience' => 'all', 'event_date' => now()->addDays(10)->toDateString()],
            ['title' => 'Annual Sports Day', 'type' => 'event', 'audience' => 'all', 'event_date' => now()->addDays(25)->toDateString()],
            ['title' => 'Library Books Due Reminder', 'type' => 'general', 'audience' => 'students', 'event_date' => null],
            ['title' => 'Staff Meeting — Term Planning', 'type' => 'general', 'audience' => 'staff', 'event_date' => null],
        ];

        foreach ($noticeDefs as $def) {
            Notice::query()->create([
                'title' => $def['title'],
                'body' => fake()->paragraph(),
                'type' => $def['type'],
                'audience' => $def['audience'],
                'event_date' => $def['event_date'],
                'is_published' => true,
                'published_at' => now()->subDays(fake()->numberBetween(1, 5)),
                'created_by' => $creator->id,
            ]);
        }

        try {
            app(AnnouncementService::class)->send([
                'title' => 'Welcome to the new term!',
                'body' => "We're excited to kick off the 2025-2026 academic year. Please check the notice board for the updated calendar.",
                'audience' => Audience::All->value,
                'channels' => ['in_app'],
            ], $creator);
        } catch (\Throwable) {
            // Broadcasting (Pusher) may not be configured in every environment this
            // seeder runs in -- the Announcement/notification rows still matter more
            // than live delivery here, so a broadcast failure shouldn't fail seeding.
        }
    }

    private function findGradeLevel(?int $id): ?GradeLevel
    {
        return $id === null ? null : ($this->gradeLevels[array_search($id, array_map(fn ($g) => $g->id, $this->gradeLevels), true)] ?? null);
    }

    private function findSection(?int $id): ?Section
    {
        if ($id === null) {
            return null;
        }
        foreach ($this->sections as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }
}
