<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Support\QrCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IdCardController extends Controller
{
    public function student(Request $request, int $id): Response
    {
        $student = Student::query()->with(['currentGradeLevel', 'currentSection'])->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $student);

        $pdf = Pdf::loadView('pdf.id-card-student', [
            'student' => $student,
            'schoolName' => tenant()->name,
            'qrCode' => QrCodeGenerator::dataUri($student->uuid),
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
            'qrCode' => QrCodeGenerator::dataUri($user->uuid),
        ])->setPaper([0, 0, 243, 153]);

        return $pdf->download(str($user->full_name.'-id-card')->slug().'.pdf');
    }
}
