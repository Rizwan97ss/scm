<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\BarcodeGenerator;
use App\Support\CardGradientGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;

class IdCardController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function student(Request $request, int $id): Response
    {
        $student = Student::query()->with(['currentGradeLevel', 'currentSection'])->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $student);

        $school = tenant();
        $primaryColor = $this->settings->get('branding.primary_color', '#2563eb', $school?->id);
        $secondaryColor = $this->settings->get('branding.secondary_color', '#0f172a', $school?->id);

        $pdf = Pdf::loadView('pdf.id-card-student', [
            'student' => $student,
            'schoolName' => $school->name,
            'logo' => $this->logoDataUri($school),
            'background' => CardGradientGenerator::diagonalDataUri($primaryColor, $secondaryColor, 486, 306),
            'barcode' => BarcodeGenerator::dataUri($student->admission_number),
            'photo' => $this->mediaDataUri($student, 'photo'),
            'accentColor' => $primaryColor,
        ])->setPaper([0, 0, 243, 153]);

        return $pdf->download(str($student->full_name.'-id-card')->slug().'.pdf');
    }

    public function staff(Request $request, int $id): Response
    {
        $user = User::query()->with('designation')->findOrFail($id);

        $this->authorize('view', $user);

        $school = tenant();

        $pdf = Pdf::loadView('pdf.id-card-staff', [
            'staff' => $user,
            'schoolName' => $school->name,
            'logo' => $this->logoDataUri($school),
            'website' => $this->tenantWebsite($school),
            'barcode' => BarcodeGenerator::dataUri($user->employee_id ?: $user->uuid),
            'photo' => $this->mediaDataUri($user, 'avatar'),
            'accentColor' => $this->settings->get('branding.primary_color', '#2563eb', $school?->id),
        ])->setPaper([0, 0, 243, 153]);

        return $pdf->download(str($user->full_name.'-id-card')->slug().'.pdf');
    }

    /**
     * dompdf can't fetch external image URLs reliably (see
     * QrCodeGenerator's docblock) — a media file's public URL is no
     * exception, so this reads it straight off disk and embeds it the same
     * way the QR code already is. Null when no photo/avatar has been
     * uploaded, so the view can fall back to an initials placeholder.
     */
    private function mediaDataUri(HasMedia $model, string $collection): ?string
    {
        $media = $model->getFirstMedia($collection);
        if (! $media) {
            return null;
        }

        $path = $media->getPath();
        if (! is_file($path)) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($path));
    }

    /**
     * branding.logo_url (see SettingSeeder) is a plain admin-entered URL,
     * not a MediaLibrary file, so it has to be fetched over HTTP rather
     * than read off disk. A short timeout and a swallowed failure keep an
     * unreachable/misconfigured URL from blocking ID card generation — the
     * view falls back to a monogram, the same graceful-degradation the
     * photo/avatar path already follows.
     */
    private function logoDataUri(?School $school): ?string
    {
        $url = $this->settings->get('branding.logo_url', '', $school?->id);
        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get($url);
            if (! $response->successful()) {
                return null;
            }

            $mime = $response->header('Content-Type') ?: 'image/png';

            return "data:{$mime};base64,".base64_encode($response->body());
        } catch (\Throwable $e) {
            Log::warning('ID card: failed to fetch branding.logo_url', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** The school's own real, working subdomain — not a fabricated placeholder — since there's no separate "school website" setting in this app. */
    private function tenantWebsite(?School $school): ?string
    {
        if (! $school) {
            return null;
        }

        $centralDomain = config('tenancy.central_domains')[0] ?? null;

        return $centralDomain ? strtoupper("{$school->slug}.{$centralDomain}") : null;
    }
}
