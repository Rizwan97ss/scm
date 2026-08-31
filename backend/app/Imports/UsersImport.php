<?php

namespace App\Imports;

use App\Imports\Concerns\CapsImportRows;
use App\Models\Designation;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Rules\ValidName;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Validators\Failure;
use Spatie\Permission\Models\Role;

/**
 * Same shape as StudentsImport/McqQuestionsImport. Two deliberate departures
 * from what a single POST /users create takes, both security-motivated:
 *
 * - No password column, ever. StoreUserRequest lets an admin set one
 *   directly for a one-off hire; a bulk file is exactly the kind of thing
 *   that ends up emailed around or left in a Downloads folder, so importing
 *   a plaintext password column would be building the leak in on purpose.
 *   Every imported account gets a random, never-exposed password and an
 *   immediate password-reset email instead — same mechanism
 *   UserController::resetPassword() already uses for "I forgot my
 *   password," just triggered proactively.
 *
 * - Every row's role assignment goes through UserPolicy::create() exactly
 *   like the single-record endpoint does, including its RESTRICTED_ROLES
 *   guard against granting "School Admin" without roles.edit. Without this,
 *   `users.import` (held by HR Staff, who is deliberately NOT granted
 *   roles.edit) would be a bulk-file bypass of a privilege-escalation
 *   control that's actively enforced everywhere else in the app.
 */
class UsersImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{
    use CapsImportRows, Importable, SkipsFailures;

    private int $importedCount = 0;

    /** @var array<string, true> normalized (trimmed, lowercased) emails already seen in this file */
    private array $seenEmails = [];

    /**
     * $dryRun runs every check below (duplicates, role existence, the
     * privilege-escalation guard, designation lookup) without writing
     * anything — the preview step the controller's `dry_run` request flag
     * drives, so a School Admin sees exactly what would happen before
     * committing to it. importedCount() means "would import" in this mode.
     */
    public function __construct(
        private readonly User $performedBy,
        private readonly UserPolicy $policy,
        private readonly bool $dryRun = false,
    ) {}

    public function onRow(Row $row): void
    {
        if ($this->overRowCap($row)) {
            return;
        }

        $data = $row->toCollection();
        $email = mb_strtolower(trim((string) $data['email']));

        if (isset($this->seenEmails[$email])) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'email',
                ['This email is a duplicate of an earlier row in this file.'],
                $data->toArray(),
            );

            return;
        }

        $roleName = trim((string) $data['role']);

        if (! Role::query()->where('name', $roleName)->exists()) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'role',
                ["\"{$roleName}\" is not an existing role."],
                $data->toArray(),
            );

            return;
        }

        if (! $this->policy->create($this->performedBy, [$roleName])) {
            $this->failures[] = new Failure(
                $row->getIndex(), 'role',
                ["You are not allowed to grant the \"{$roleName}\" role."],
                $data->toArray(),
            );

            return;
        }

        $designationId = null;
        $designationName = trim((string) ($data['designation_name'] ?? ''));

        if ($designationName !== '') {
            $designation = Designation::query()->where('name', $designationName)->first();

            if (! $designation) {
                $this->failures[] = new Failure(
                    $row->getIndex(), 'designation_name',
                    ["No designation named \"{$designationName}\" was found."],
                    $data->toArray(),
                );

                return;
            }

            $designationId = $designation->id;
        }

        $this->seenEmails[$email] = true;

        if (! $this->dryRun) {
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make(Str::password(40)),
                'must_change_password' => true,
                'status' => 'active',
                'designation_id' => $designationId,
                'employee_id' => $data['employee_id'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
            ]);

            $user->assignRole($roleName);

            Password::sendResetLink(['email' => $email]);
        }

        $this->importedCount++;
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', new ValidName],
            'last_name' => ['required', 'string', 'max:100', new ValidName],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation_name' => ['nullable', 'string', 'max:150'],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')],
            'hire_date' => ['nullable', 'date'],
        ];
    }
}
