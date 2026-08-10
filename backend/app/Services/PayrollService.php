<?php

namespace App\Services;

use App\Enums\PayslipStatus;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk payslip generation, salary snapshotting, and mark-paid — the payroll
 * analogue of InvoiceService.
 */
class PayrollService
{
    public function __construct(
        private readonly IdSequenceService $idSequences,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Same shape as FeeNumberService (Phase 8) — a DB-driven format setting
     * (group 'payroll' here rather than 'fees', since payslip numbering is
     * an HR concern, not a billing one) backed by IdSequenceService's
     * race-safe per-school counter. Not reusing FeeNumberService itself:
     * despite the identical mechanism, coupling payroll to a fees-named
     * service would be a confusing cross-module dependency for what's
     * really just IdSequenceService used a second time.
     */
    private function nextPayslipNumber(School $school): string
    {
        $year = Carbon::now()->year;
        $format = (string) $this->settings->get('payroll.payslip_number_format', 'PS-{YEAR}-{SEQ}', $school->id);
        $padding = (int) $this->settings->get('payroll.number_padding', 4, $school->id);
        $sequence = $this->idSequences->next($school->id, "payslip_number:{$year}");

        return strtr($format, [
            '{SCHOOL}' => $school->short_name,
            '{YEAR}' => (string) $year,
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * One invoice per active SalaryStructure for the school, skipping anyone
     * who already has a payslip for that month/year.
     *
     * Deliberately one transaction PER STAFF MEMBER, not one transaction
     * around the whole batch — see InvoiceService::generateFromStructure()'s
     * docblock (and docs/database.md § Key modeling decisions) for the real,
     * reproducible "unable to open database file" bug a single big
     * transaction caused under `php artisan serve` in Phase 8. Applying that
     * lesson from the start here rather than rediscovering it.
     *
     * @return array{created: Collection<int, Payslip>, skipped_count: int}
     */
    public function generateForMonth(School $school, int $month, int $year, User $creator): array
    {
        $structures = SalaryStructure::query()
            ->where('is_active', true)
            ->get();

        $alreadyPaidUserIds = Payslip::query()
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('user_id')
            ->all();

        $created = collect();
        $skippedCount = 0;

        foreach ($structures as $structure) {
            if (in_array($structure->user_id, $alreadyPaidUserIds, true)) {
                $skippedCount++;

                continue;
            }

            $created->push($this->generateOnePayslip($school, $structure, $month, $year, $creator));
        }

        return ['created' => $created, 'skipped_count' => $skippedCount];
    }

    private function generateOnePayslip(School $school, SalaryStructure $structure, int $month, int $year, User $creator): Payslip
    {
        return DB::transaction(fn () => Payslip::query()->create([
            'user_id' => $structure->user_id,
            'salary_structure_id' => $structure->id,
            'payslip_number' => $this->nextPayslipNumber($school),
            'month' => $month,
            'year' => $year,
            'basic_salary' => $structure->basic_salary,
            'allowances' => $structure->allowances,
            'deductions' => $structure->deductions,
            'net_salary' => $structure->net_salary,
            'status' => PayslipStatus::Generated,
            'generated_by' => $creator->id,
        ]));
    }

    public function markPaid(Payslip $payslip): void
    {
        abort_if($payslip->status === PayslipStatus::Paid, 422, 'This payslip is already marked paid.');

        $payslip->update(['status' => PayslipStatus::Paid, 'paid_at' => now()->toDateString()]);
    }

    /**
     * When a new structure is created for a user, the previous active one
     * (if any) is closed rather than left dangling — "only one active
     * structure per user" is enforced here, not by a DB constraint, since
     * historical (inactive) structures must still exist for old payslips'
     * salary_structure_id to resolve.
     */
    public function replaceActiveStructure(int $userId, string $effectiveFrom): void
    {
        SalaryStructure::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false, 'effective_to' => $effectiveFrom]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payslipData(Payslip $payslip): array
    {
        $payslip->loadMissing('user');

        return [
            'payslip' => [
                'payslip_number' => $payslip->payslip_number,
                'month' => $payslip->month,
                'year' => $payslip->year,
                'basic_salary' => $payslip->basic_salary,
                'allowances' => $payslip->allowances,
                'deductions' => $payslip->deductions,
                'net_salary' => $payslip->net_salary,
                'status' => $payslip->status->label(),
                'paid_at' => $payslip->paid_at?->toDateString(),
            ],
            'employee' => [
                'full_name' => $payslip->user->full_name,
                'employee_id' => $payslip->user->employee_id,
            ],
        ];
    }
}
