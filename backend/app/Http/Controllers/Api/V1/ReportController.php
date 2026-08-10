<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AcademicReportService;
use App\Services\AttendanceReportService;
use App\Services\EnrollmentReportService;
use App\Services\OperationsReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Cross-module analytics — the fuller reports surface FeeReportService's
 * docblock pointed at back in Phase 8. Each action reuses the permission of
 * the module it reports on rather than a standalone `reports.*` permission
 * (a report is just a different shape of "view" for the same data), the
 * same way `invoices.view-reports` hangs off the existing `invoices`
 * module instead of a generic one. No Policy class backs these — they
 * aren't tied to a single Eloquent model, so authorization is a direct
 * permission check, the same shape `platform.*` uses.
 */
class ReportController extends Controller
{
    public function attendance(Request $request, AttendanceReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('student-attendance.view') || $user->can('staff-attendance.view'), 403);

        $from = $request->filled('from_date') ? Carbon::parse($request->string('from_date')->toString()) : now()->subMonths(5)->startOfMonth();
        $to = $request->filled('to_date') ? Carbon::parse($request->string('to_date')->toString()) : now();

        return ApiResponse::success($reports->summary($user->school, $user, $from, $to));
    }

    public function academicPerformance(Request $request, AcademicReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('exam-marks.view'), 403);

        return ApiResponse::success(['exams' => $reports->recentExamPerformance($user->school)]);
    }

    public function enrollment(Request $request, EnrollmentReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('students.view'), 403);

        return ApiResponse::success($reports->summary($user->school));
    }

    public function operations(Request $request, OperationsReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('library.view') || $user->can('transport.view') || $user->can('hostel.view'), 403);

        return ApiResponse::success($reports->summary($user->school, $user));
    }
}
