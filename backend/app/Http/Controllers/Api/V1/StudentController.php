<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentGuardianRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentGuardianRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\PlanLimitService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StudentController extends Controller
{
    private const WITH = ['currentGradeLevel', 'currentSection', 'currentDepartment', 'guardians'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $paginator = QueryBuilder::for(Student::query()->with(self::WITH)->visibleTo($request->user()))
            ->allowedFilters(
                'first_name', 'last_name', 'admission_number', 'status',
                AllowedFilter::exact('current_grade_level_id'),
                AllowedFilter::exact('current_section_id'),
                AllowedFilter::exact('academic_year_id'),
            )
            ->allowedSorts('first_name', 'last_name', 'admission_number', 'admission_date', 'created_at')
            ->defaultSort('first_name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(StudentResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreStudentRequest $request, StudentIdGeneratorService $idGenerator, StudentEnrollmentService $enrollment, PlanLimitService $planLimits): JsonResponse
    {
        $this->authorize('create', Student::class);

        // Super Admin (PlatformUser) can't reach this route at all -- it's
        // tenant-scoped, on the tenant route group's guard, so $request->user()
        // is always a tenant User here. No bypass needed.
        $planLimits->assertCanAddStudent(tenant());

        $student = DB::transaction(function () use ($request, $idGenerator, $enrollment) {
            $school = tenant();

            $student = Student::query()->create([
                ...$request->safe()->except('guardians'),
                'admission_number' => $idGenerator->generate($school, $request->date('admission_date')),
            ]);

            foreach ($request->input('guardians', []) as $guardianInput) {
                $guardianAttributes = [
                    'first_name' => $guardianInput['first_name'],
                    'last_name' => $guardianInput['last_name'],
                    'email' => $guardianInput['email'] ?? null,
                    'phone' => $guardianInput['phone'],
                ];

                // Same email-reuse rule as StudentsImport/GuardiansImport --
                // two siblings admitted separately, or a guardian re-entered
                // by hand on a later admission, get one Guardian row, not a
                // duplicate. No email means no reliable key, so it's always
                // a fresh row in that case (matches the import paths too).
                $guardian = match (true) {
                    isset($guardianInput['guardian_id']) => Guardian::query()->findOrFail($guardianInput['guardian_id']),
                    ! empty($guardianInput['email']) => Guardian::query()->firstOrCreate(['email' => $guardianInput['email']], $guardianAttributes),
                    default => Guardian::query()->create($guardianAttributes),
                };

                $student->guardians()->attach($guardian->id, [
                    'relationship_type' => $guardianInput['relationship_type'],
                    'is_primary' => $guardianInput['is_primary'] ?? false,
                    'can_pickup' => $guardianInput['can_pickup'] ?? true,
                ]);
            }

            $enrollment->recordAdmission($student, $request->user());

            return $student;
        });

        return ApiResponse::created(new StudentResource($student->load(self::WITH)));
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        return ApiResponse::success(new StudentResource($student->load(self::WITH)));
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $student->update($request->validated());

        return ApiResponse::success(new StudentResource($student->load(self::WITH)));
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('delete', $student);

        $student->delete();

        return ApiResponse::noContent();
    }

    public function attachGuardian(StoreStudentGuardianRequest $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $guardianAttributes = $request->only(['first_name', 'last_name', 'email', 'phone', 'occupation']);

        // See store()'s matching comment -- reuse by email instead of
        // always minting a new Guardian row.
        $guardian = match (true) {
            $request->filled('guardian_id') => Guardian::query()->findOrFail($request->integer('guardian_id')),
            $request->filled('email') => Guardian::query()->firstOrCreate(['email' => $request->string('email')->toString()], $guardianAttributes),
            default => Guardian::query()->create($guardianAttributes),
        };

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship_type' => $request->string('relationship_type')->toString(),
                'is_primary' => $request->boolean('is_primary'),
                'can_pickup' => $request->boolean('can_pickup', true),
            ],
        ]);

        return ApiResponse::created(new StudentResource($student->load(self::WITH)));
    }

    public function updateGuardianLink(UpdateStudentGuardianRequest $request, Student $student, Guardian $guardian): JsonResponse
    {
        $this->authorize('update', $student);

        $student->guardians()->updateExistingPivot($guardian->id, $request->validated());

        return ApiResponse::success(new StudentResource($student->load(self::WITH)));
    }

    public function detachGuardian(Student $student, Guardian $guardian): JsonResponse
    {
        $this->authorize('update', $student);

        $student->guardians()->detach($guardian->id);

        return ApiResponse::noContent();
    }

    /**
     * Creates (or reuses) a portal login for the student. Students rarely have a
     * real personal inbox, so unlike staff/guardian invites this logs in via
     * username (the admission number) and returns a one-time temporary password
     * for the admin to hand to the student directly, rather than emailing a link.
     */
    public function invitePortalUser(Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $temporaryPassword = Str::password(12);

        if ($student->user_id) {
            $student->user->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'status' => 'active',
            ])->save();
        } else {
            $school = tenant();

            $user = User::query()->create([
                'username' => $student->admission_number,
                'email' => "{$student->admission_number}@{$school->slug}.students.local",
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Student');
            $student->update(['user_id' => $user->id]);
        }

        return ApiResponse::success([
            'username' => $student->fresh()->user->username,
            'temporary_password' => $temporaryPassword,
        ], 'Portal account ready. Share these credentials with the student directly — they will be required to set a new password on first login.');
    }
}
