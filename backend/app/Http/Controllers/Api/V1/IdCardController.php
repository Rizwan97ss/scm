<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\QrCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\HasMedia;

class IdCardController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function student(Request $request, int $id): Response
    {
        $student = Student::query()->with(['currentGradeLevel', 'currentSection'])->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $student);

        $pdf = Pdf::loadView('pdf.id-card-student', [
            'student' => $student,
            'schoolName' => tenant()->name,
            'qrCode' => QrCodeGenerator::dataUri($student->uuid, 120),
            'photo' => $this->mediaDataUri($student, 'photo'),
            'accentColor' => $this->settings->get('branding.primary_color', '#2563eb', tenant()?->id),
        ])->setPaper([0, 0, 243, 153]);

        return $pdf->download(str($student->full_name.'-id-card')->slug().'.pdf');
    }

    public function staff(Request $request, int $id): Response
    {
        $user = User::query()->with('designation')->findOrFail($id);

        $this->authorize('view', $user);

        $pdf = Pdf::loadView('pdf.id-card-staff', [
            'staff' => $user,
            'schoolName' => tenant()->name,
            'qrCode' => QrCodeGenerator::dataUri($user->uuid, 120),
            'photo' => $this->mediaDataUri($user, 'avatar'),
            'accentColor' => $this->settings->get('branding.primary_color', '#2563eb', tenant()?->id),
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
}
