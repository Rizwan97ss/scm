<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off backfill for Sub-phase A's PII encryption: rewrites already-stored
 * plaintext values in the columns that just gained an `encrypted` cast
 * (User::phone, Student::medical_info/emergency_contact_phone/address_line1/
 * address_line2, Guardian::national_id/address_line1/address_line2,
 * Payment::reference_number) so they match what the cast will expect to
 * decrypt on the next read.
 *
 * Writes via DB::table() directly, not the Eloquent models — the models
 * already carry the encrypted cast by the time this runs, and reading a
 * still-plaintext row through them would throw DecryptException immediately.
 * Uses Crypt::encryptString() (the same encrypter the cast itself calls),
 * so the format is byte-compatible with what Eloquent reads afterward.
 *
 * Deploy order (see docs/deployment.md): run the widening migration, then
 * this command, THEN deploy code with the encrypted casts live. Running it
 * out of order (casts already live, command not yet run) makes every
 * existing row unreadable until this command completes.
 *
 * Idempotent: a value that already decrypts successfully is assumed
 * already-encrypted and left alone, so re-running this command (e.g. after
 * a partial failure, or a newly-provisioned tenant that already encrypts on
 * write) is always safe.
 */
class EncryptExistingPiiCommand extends Command
{
    protected $signature = 'security:encrypt-pii {--dry-run : Report what would change without writing anything} {--school= : Only process the school with this slug}';

    protected $description = 'Encrypt existing plaintext PII columns in every tenant database (one-off backfill for the encrypted casts added in Phase 15)';

    /** @var array<string, array<int, string>> */
    private const TARGETS = [
        'users' => ['phone'],
        'students' => ['medical_info', 'emergency_contact_phone', 'address_line1', 'address_line2'],
        'guardians' => ['national_id', 'address_line1', 'address_line2'],
        'payments' => ['reference_number'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlySlug = $this->option('school');

        $schools = School::query()->when($onlySlug, fn ($q) => $q->where('slug', $onlySlug))->get();

        if ($schools->isEmpty()) {
            $this->error('No matching school found.');

            return self::FAILURE;
        }

        foreach ($schools as $school) {
            // Degrade, don't crash the whole batch — see School::studentCount()'s own guard.
            if (! $school->database()->manager()->databaseExists($school->database()->getName())) {
                $this->warn("=== {$school->name} ({$school->slug}) — skipped (no physical database) ===");

                continue;
            }

            $this->info("=== {$school->name} ({$school->slug}) ===");

            $school->run(function () use ($dryRun) {
                foreach (self::TARGETS as $table => $columns) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    $this->encryptTable($table, $columns, $dryRun);
                }
            });
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes were written.');
        }

        return self::SUCCESS;
    }

    /** @param  array<int, string>  $columns */
    private function encryptTable(string $table, array $columns, bool $dryRun): void
    {
        $rows = DB::table($table)->select(['id', ...$columns])->get();
        $encryptedCount = 0;

        foreach ($rows as $row) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $row->{$column};

                if ($value === null || $value === '' || $this->isAlreadyEncrypted($value)) {
                    continue;
                }

                $updates[$column] = Crypt::encryptString($value);
            }

            if ($updates === []) {
                continue;
            }

            $encryptedCount++;

            if (! $dryRun) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        }

        $this->line("  {$table}: {$encryptedCount} row(s) ".($dryRun ? 'would be' : 'were').' encrypted');
    }

    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
