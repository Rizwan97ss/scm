<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\School;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TODO(tenancy): needs Sub-phase E's rewrite, not a patch -- every model
 * created below (AcademicYear, Section, User, ...) now lives in a tenant's
 * own database. This seeder must run inside $school->run(fn () => ...) /
 * tenancy()->initialize($school) for any of these creates to land in the
 * right place at all; today it silently writes to whatever connection
 * happens to be active. Left as a known-broken placeholder.
 */
class AcademicStructureDemoSeeder extends Seeder
{
    private School $school;

    public function run(): void
    {
        $this->school = School::query()->where('short_name', 'demo')->firstOrFail();

        $academicYear = $this->academicYear();
        $this->terms($academicYear);
        $departments = $this->departments();
        $gradeLevels = $this->gradeLevels();
        $rooms = $this->rooms();
        $sections = $this->sections($academicYear, $gradeLevels, $rooms);
        $subjects = $this->subjects($departments);
        $this->timetablePeriods();
        $staff = $this->staffUsers();
        $this->assignClassTeachers($sections, $staff);
        $this->classSubjectTeachers($academicYear, $sections, $subjects, $staff);
        $this->timetableEntries($academicYear, $sections, $subjects, $staff);
        $this->holidays($academicYear);
    }

    private function academicYear(): AcademicYear
    {
        return AcademicYear::query()->updateOrCreate(
            ['school_id' => $this->school->id, 'name' => '2025-2026'],
            ['start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']
        );
    }

    private function terms(AcademicYear $year): void
    {
        $terms = [
            ['name' => 'Term 1', 'start_date' => '2025-09-01', 'end_date' => '2026-01-16', 'sequence' => 1, 'is_current' => false],
            ['name' => 'Term 2', 'start_date' => '2026-01-19', 'end_date' => '2026-06-30', 'sequence' => 2, 'is_current' => true],
        ];

        foreach ($terms as $term) {
            Term::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => $term['name']],
                $term
            );
        }
    }

    /** @return array<string, Department> */
    private function departments(): array
    {
        $departments = [
            ['code' => 'MATSCI', 'name' => 'Mathematics & Science'],
            ['code' => 'HUMLANG', 'name' => 'Languages & Humanities'],
            ['code' => 'ARTPE', 'name' => 'Arts & Physical Education'],
        ];

        $result = [];
        foreach ($departments as $department) {
            $result[$department['code']] = Department::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'code' => $department['code']],
                $department
            );
        }

        return $result;
    }

    /** @return array<int, GradeLevel> keyed by sequence */
    private function gradeLevels(): array
    {
        $levels = ['Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5'];

        $result = [];
        foreach ($levels as $sequence => $name) {
            $code = $sequence === 0 ? 'K' : "G{$sequence}";
            $result[$sequence] = GradeLevel::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'code' => $code],
                ['name' => $name, 'sequence' => $sequence]
            );
        }

        return $result;
    }

    /** @return array<int, Room> */
    private function rooms(): array
    {
        $rooms = [
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

        $result = [];
        foreach ($rooms as $room) {
            $result[$room['code']] = Room::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'code' => $room['code']],
                $room
            );
        }

        return $result;
    }

    /**
     * @param  array<int, GradeLevel>  $gradeLevels
     * @param  array<string, Room>  $rooms
     * @return array<string, Section> keyed by "{gradeSequence}-{A|B}"
     */
    private function sections(AcademicYear $year, array $gradeLevels, array $rooms): array
    {
        $roomCodes = array_values(array_filter(array_keys($rooms), fn ($code) => str_starts_with($code, 'R')));

        $result = [];
        $roomIndex = 0;
        foreach ($gradeLevels as $sequence => $gradeLevel) {
            foreach (['A', 'B'] as $letter) {
                $room = $rooms[$roomCodes[$roomIndex % count($roomCodes)]];
                $roomIndex++;

                $result["{$sequence}-{$letter}"] = Section::query()->updateOrCreate(
                    [
                        'school_id' => $this->school->id,
                        'academic_year_id' => $year->id,
                        'grade_level_id' => $gradeLevel->id,
                        'name' => $letter,
                    ],
                    ['capacity' => 25, 'room_id' => $room->id]
                );
            }
        }

        return $result;
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Subject>
     */
    private function subjects(array $departments): array
    {
        $subjects = [
            ['code' => 'MATH', 'name' => 'Mathematics', 'department' => 'MATSCI'],
            ['code' => 'SCI', 'name' => 'Science', 'department' => 'MATSCI'],
            ['code' => 'ENG', 'name' => 'English Language', 'department' => 'HUMLANG'],
            ['code' => 'SOC', 'name' => 'Social Studies', 'department' => 'HUMLANG'],
            ['code' => 'ART', 'name' => 'Art', 'department' => 'ARTPE', 'is_elective' => true],
            ['code' => 'PE', 'name' => 'Physical Education', 'department' => 'ARTPE'],
        ];

        $result = [];
        foreach ($subjects as $subject) {
            $result[$subject['code']] = Subject::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'code' => $subject['code']],
                [
                    'name' => $subject['name'],
                    'department_id' => $departments[$subject['department']]->id,
                    'is_elective' => $subject['is_elective'] ?? false,
                ]
            );
        }

        return $result;
    }

    private function timetablePeriods(): void
    {
        $periods = [
            ['name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'sequence' => 1, 'is_break' => false],
            ['name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'sequence' => 2, 'is_break' => false],
            ['name' => 'Break', 'start_time' => '09:30', 'end_time' => '09:45', 'sequence' => 3, 'is_break' => true],
            ['name' => 'Period 3', 'start_time' => '09:45', 'end_time' => '10:30', 'sequence' => 4, 'is_break' => false],
            ['name' => 'Period 4', 'start_time' => '10:30', 'end_time' => '11:15', 'sequence' => 5, 'is_break' => false],
            ['name' => 'Lunch', 'start_time' => '11:15', 'end_time' => '12:00', 'sequence' => 6, 'is_break' => true],
            ['name' => 'Period 5', 'start_time' => '12:00', 'end_time' => '12:45', 'sequence' => 7, 'is_break' => false],
            ['name' => 'Period 6', 'start_time' => '12:45', 'end_time' => '13:30', 'sequence' => 8, 'is_break' => false],
        ];

        foreach ($periods as $period) {
            TimetablePeriod::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'name' => $period['name']],
                $period
            );
        }
    }

    /** @return array<string, User> keyed by a short handle */
    private function staffUsers(): array
    {
        $staff = [
            'school_admin' => ['role' => 'School Admin', 'first' => 'Alice', 'last' => 'Admin', 'email' => 'admin@riverside-demo.test'],
            'principal' => ['role' => 'Principal', 'first' => 'Patricia', 'last' => 'Principal', 'email' => 'principal@riverside-demo.test'],
            'teacher_math' => ['role' => 'Class Teacher', 'first' => 'Thomas', 'last' => 'Teacher', 'email' => 'thomas.teacher@riverside-demo.test'],
            'teacher_english' => ['role' => 'Class Teacher', 'first' => 'Teresa', 'last' => 'Teacher', 'email' => 'teresa.teacher@riverside-demo.test'],
            'teacher_science' => ['role' => 'Teacher', 'first' => 'Sam', 'last' => 'Scientist', 'email' => 'sam.scientist@riverside-demo.test'],
            'accountant' => ['role' => 'Accountant', 'first' => 'Andy', 'last' => 'Accountant', 'email' => 'accountant@riverside-demo.test'],
            'hr' => ['role' => 'HR Staff', 'first' => 'Helen', 'last' => 'HR', 'email' => 'hr@riverside-demo.test'],
            'receptionist' => ['role' => 'Receptionist', 'first' => 'Rachel', 'last' => 'Reception', 'email' => 'reception@riverside-demo.test'],
            'librarian' => ['role' => 'Librarian', 'first' => 'Larry', 'last' => 'Librarian', 'email' => 'librarian@riverside-demo.test'],
            'transport' => ['role' => 'Transport Staff', 'first' => 'Tina', 'last' => 'Transport', 'email' => 'transport@riverside-demo.test'],
        ];

        $result = [];
        foreach ($staff as $handle => $person) {
            $user = User::query()->updateOrCreate(
                ['email' => $person['email']],
                [
                    'school_id' => $this->school->id,
                    'first_name' => $person['first'],
                    'last_name' => $person['last'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole($person['role'])) {
                $user->assignRole($person['role']);
            }

            $result[$handle] = $user;
        }

        $this->command?->info('Demo staff seeded (password for all: "password"): '.implode(', ', array_column($staff, 'email')));

        return $result;
    }

    /**
     * @param  array<string, Section>  $sections
     * @param  array<string, User>  $staff
     */
    private function assignClassTeachers(array $sections, array $staff): void
    {
        $sections['1-A']->update(['class_teacher_id' => $staff['teacher_math']->id]);
        $sections['2-A']->update(['class_teacher_id' => $staff['teacher_english']->id]);
    }

    /**
     * @param  array<string, Section>  $sections
     * @param  array<string, Subject>  $subjects
     * @param  array<string, User>  $staff
     */
    private function classSubjectTeachers(AcademicYear $year, array $sections, array $subjects, array $staff): void
    {
        $assignments = [
            ['section' => '1-A', 'subject' => 'MATH', 'teacher' => 'teacher_math'],
            ['section' => '1-A', 'subject' => 'SCI', 'teacher' => 'teacher_science'],
            ['section' => '2-A', 'subject' => 'ENG', 'teacher' => 'teacher_english'],
            ['section' => '2-A', 'subject' => 'SCI', 'teacher' => 'teacher_science'],
            ['section' => '1-B', 'subject' => 'SCI', 'teacher' => 'teacher_science'],
        ];

        foreach ($assignments as $assignment) {
            ClassSubjectTeacher::query()->updateOrCreate(
                [
                    'school_id' => $this->school->id,
                    'academic_year_id' => $year->id,
                    'section_id' => $sections[$assignment['section']]->id,
                    'subject_id' => $subjects[$assignment['subject']]->id,
                ],
                ['teacher_id' => $staff[$assignment['teacher']]->id]
            );
        }
    }

    /**
     * @param  array<string, Section>  $sections
     * @param  array<string, Subject>  $subjects
     * @param  array<string, User>  $staff
     */
    private function timetableEntries(AcademicYear $year, array $sections, array $subjects, array $staff): void
    {
        $period1 = TimetablePeriod::query()->where('school_id', $this->school->id)->where('name', 'Period 1')->firstOrFail();
        $period2 = TimetablePeriod::query()->where('school_id', $this->school->id)->where('name', 'Period 2')->firstOrFail();

        $entries = [
            ['section' => '1-A', 'subject' => 'MATH', 'teacher' => 'teacher_math', 'period' => $period1, 'day' => 1],
            ['section' => '1-A', 'subject' => 'SCI', 'teacher' => 'teacher_science', 'period' => $period2, 'day' => 1],
            ['section' => '2-A', 'subject' => 'ENG', 'teacher' => 'teacher_english', 'period' => $period1, 'day' => 2],
        ];

        foreach ($entries as $entry) {
            TimetableEntry::query()->updateOrCreate(
                [
                    'school_id' => $this->school->id,
                    'academic_year_id' => $year->id,
                    'section_id' => $sections[$entry['section']]->id,
                    'timetable_period_id' => $entry['period']->id,
                    'day_of_week' => $entry['day'],
                ],
                [
                    'subject_id' => $subjects[$entry['subject']]->id,
                    'teacher_id' => $staff[$entry['teacher']]->id,
                    'room_id' => $sections[$entry['section']]->room_id,
                ]
            );
        }
    }

    private function holidays(AcademicYear $year): void
    {
        $holidays = [
            ['name' => 'Winter Break', 'start_date' => '2025-12-22', 'end_date' => '2026-01-02', 'type' => 'school_specific'],
            ['name' => 'Spring Break', 'start_date' => '2026-03-16', 'end_date' => '2026-03-20', 'type' => 'school_specific'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => $holiday['name']],
                $holiday
            );
        }
    }
}
