<?php

namespace App\Services;

use App\Enums\DataExportScope;
use App\Enums\DataExportStatus;
use App\Exports\GenericCsvExport;
use App\Models\DataExport;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use ZipArchive;

/**
 * Builds a downloadable ZIP of CSVs for a DataExport row — self-service
 * (the requester's own visible rows, reusing the same scopeVisibleTo()
 * scopes the UI already uses, zero new authorization logic) or admin bulk
 * (every row in the tenant, unscoped). Runs inside GenerateDataExportJob,
 * already tenant-context-aware via stancl's QueueTenancyBootstrapper (see
 * config/tenancy.php) — no manual $school->run() needed here.
 */
class DataExportService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function generate(DataExport $export): void
    {
        $export->update(['status' => DataExportStatus::Processing]);

        try {
            $datasets = $export->scope === DataExportScope::Self
                ? $this->selfServiceDatasets($export->requestedBy)
                : $this->schoolDatasets();

            $path = $this->buildZip($export, $datasets);

            $export->update([
                'status' => DataExportStatus::Ready,
                'file_path' => $path,
                'expires_at' => now()->addDays((int) $this->settings->get('retention.data_export_days', 7)),
            ]);
        } catch (Throwable $e) {
            $export->update(['status' => DataExportStatus::Failed, 'failure_reason' => substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }

    /** @return array<string, Collection<int, array<int, mixed>>> dataset name => [headings, ...rows] where index 0 is the headings row */
    private function selfServiceDatasets(User $user): array
    {
        $datasets = [
            'account' => collect([
                ['Field', 'Value'],
                ['Name', $user->full_name],
                ['Email', $user->email],
                ['Username', $user->username],
                ['Phone', $user->phone],
                ['Status', $user->status?->value],
                ['Roles', $user->getRoleNames()->implode(', ')],
                ['Last login', $user->last_login_at?->toIso8601String()],
                ['Account created', $user->created_at?->toIso8601String()],
            ]),
        ];

        if ($student = $user->studentProfile) {
            $datasets['student_profile'] = collect([
                ['Field', 'Value'],
                ['Admission number', $student->admission_number],
                ['Name', $student->full_name],
                ['Grade level', $student->currentGradeLevel?->name],
                ['Section', $student->currentSection?->name],
                ['Date of birth', $student->date_of_birth?->toDateString()],
                ['Admission date', $student->admission_date?->toDateString()],
                ['Status', $student->status?->value],
            ]);
        }

        if ($guardian = $user->guardianProfile) {
            $datasets['guardian_profile'] = collect([
                ['Field', 'Value'],
                ['Name', $guardian->full_name],
                ['Email', $guardian->email],
                ['Phone', $guardian->phone],
                ['Occupation', $guardian->occupation],
            ]);
        }

        $datasets['invoices'] = $this->rowsWithHeadings(
            Invoice::query()->visibleTo($user)->with('student')->get(),
            ['Invoice number', 'Student', 'Issue date', 'Due date', 'Status', 'Total', 'Amount paid'],
            fn (Invoice $i) => [$i->invoice_number, $i->student?->full_name, $i->issue_date?->toDateString(), $i->due_date?->toDateString(), $i->status?->value, $i->total, $i->amount_paid]
        );

        $datasets['payments'] = $this->rowsWithHeadings(
            Payment::query()->visibleTo($user)->with('student')->get(),
            ['Payment number', 'Student', 'Amount', 'Method', 'Paid at'],
            fn (Payment $p) => [$p->payment_number, $p->student?->full_name, $p->amount, $p->method?->value, $p->paid_at?->toDateString()]
        );

        return $datasets;
    }

    /** @return array<string, Collection<int, array<int, mixed>>> */
    private function schoolDatasets(): array
    {
        return [
            'users' => $this->rowsWithHeadings(
                User::query()->with('roles')->get(),
                ['Name', 'Email', 'Username', 'Status', 'Roles', 'Last login'],
                fn (User $u) => [$u->full_name, $u->email, $u->username, $u->status?->value, $u->getRoleNames()->implode(', '), $u->last_login_at?->toIso8601String()]
            ),
            'students' => $this->rowsWithHeadings(
                Student::query()->with(['currentGradeLevel', 'currentSection'])->get(),
                ['Admission number', 'Name', 'Grade level', 'Section', 'Status'],
                fn (Student $s) => [$s->admission_number, $s->full_name, $s->currentGradeLevel?->name, $s->currentSection?->name, $s->status?->value]
            ),
            'guardians' => $this->rowsWithHeadings(
                Guardian::query()->get(),
                ['Name', 'Email', 'Phone', 'Occupation'],
                fn (Guardian $g) => [$g->full_name, $g->email, $g->phone, $g->occupation]
            ),
            'invoices' => $this->rowsWithHeadings(
                Invoice::query()->with('student')->get(),
                ['Invoice number', 'Student', 'Issue date', 'Due date', 'Status', 'Total', 'Amount paid'],
                fn (Invoice $i) => [$i->invoice_number, $i->student?->full_name, $i->issue_date?->toDateString(), $i->due_date?->toDateString(), $i->status?->value, $i->total, $i->amount_paid]
            ),
            'payments' => $this->rowsWithHeadings(
                Payment::query()->with('student')->get(),
                ['Payment number', 'Student', 'Amount', 'Method', 'Paid at'],
                fn (Payment $p) => [$p->payment_number, $p->student?->full_name, $p->amount, $p->method?->value, $p->paid_at?->toDateString()]
            ),
        ];
    }

    /**
     * @param  iterable<int, mixed>  $models
     * @param  array<int, string>  $headings
     * @param  callable(mixed): array<int, mixed>  $map
     * @return Collection<int, array<int, mixed>>
     */
    private function rowsWithHeadings(iterable $models, array $headings, callable $map): Collection
    {
        return collect([$headings, ...collect($models)->map($map)]);
    }

    /**
     * Built on the 'local' disk specifically (not the app's configured
     * default, see FILESYSTEM_DISK in docs/deployment.md §7) — ZipArchive
     * needs a real filesystem path, which only a local disk guarantees.
     * Fine at this app's current scale; a future S3 deployment would need
     * an extra upload-the-finished-zip step this doesn't do yet.
     *
     * @param  array<string, Collection<int, array<int, mixed>>>  $datasets
     */
    private function buildZip(DataExport $export, array $datasets): string
    {
        $tempDir = "tmp/data-export-{$export->id}";
        $disk = Storage::disk('local');

        foreach ($datasets as $name => $rows) {
            $headings = $rows->first() ?? [];
            $body = $rows->slice(1)->values();
            Excel::store(new GenericCsvExport($headings, $body), "{$tempDir}/{$name}.csv", 'local', ExcelFormat::CSV);
        }

        $disk->makeDirectory('data-exports');
        $zipRelativePath = "data-exports/export-{$export->id}.zip";

        $zip = new ZipArchive;
        $zip->open($disk->path($zipRelativePath), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (array_keys($datasets) as $name) {
            $zip->addFile($disk->path("{$tempDir}/{$name}.csv"), "{$name}.csv");
        }
        $zip->close();

        $disk->deleteDirectory($tempDir);

        return $zipRelativePath;
    }
}
