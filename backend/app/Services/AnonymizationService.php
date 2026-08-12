<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Scrubs PII while preserving what's legally/operationally required to
 * survive an account's removal — the exact line is drawn per-model, not
 * "delete everything":
 *
 * - Student: contact/medical/address fields are scrubbed, but the name and
 *   every academic record (grade level, enrollment history, exam marks,
 *   attendance) stay intact — those belong to the *student's own* ongoing
 *   academic identity, not the login account being removed, and a
 *   transcript/certificate for an alumnus should still show their real
 *   name even after their portal login is gone.
 * - Guardian: scrubbed in full (name included) — a guardian's record has
 *   no equivalent "academic identity" reason to survive.
 * - Invoice/Payment/CreditNote/Payslip: never touched here at all —
 *   financial/legal record, retained in full. None of them carry
 *   redundant name/address PII of their own; anonymizing the parent
 *   User/Student row is sufficient.
 * - activity_log: retained as-is, including historical pre-anonymization
 *   snapshots — a deliberate, standard audit-trail retention exception,
 *   not scrubbed retroactively.
 */
class AnonymizationService
{
    public function anonymizeUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($student = $user->studentProfile) {
                $student->update([
                    'medical_info' => null,
                    'emergency_contact_name' => null,
                    'emergency_contact_phone' => null,
                    'address_line1' => null,
                    'address_line2' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'country' => null,
                    'previous_school_name' => null,
                    'previous_school_details' => null,
                ]);
            }

            if ($guardian = $user->guardianProfile) {
                $guardian->update([
                    'first_name' => 'Deleted',
                    'last_name' => 'Guardian',
                    'email' => null,
                    'phone' => 'anonymized',
                    'national_id' => null,
                    'occupation' => null,
                    'address_line1' => null,
                    'address_line2' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'country' => null,
                ]);
            }

            $user->clearMediaCollection('avatar');

            $user->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => "deleted-{$user->uuid}@anonymized.invalid",
                'username' => null,
                'phone' => null,
                'date_of_birth' => null,
                'status' => UserStatus::Inactive,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        });
    }
}
