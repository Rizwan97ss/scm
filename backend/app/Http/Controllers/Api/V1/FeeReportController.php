<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\FeeReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeReportController extends Controller
{
    public function collectionSummary(Request $request, FeeReportService $reports): JsonResponse
    {
        $this->authorize('viewReports', Invoice::class);

        return ApiResponse::success($reports->collectionSummary(
            $request->user()->school,
            $request->string('from_date')->toString() ?: null,
            $request->string('to_date')->toString() ?: null,
        ));
    }

    public function outstandingDues(Request $request, FeeReportService $reports): JsonResponse
    {
        $this->authorize('viewReports', Invoice::class);

        return ApiResponse::success($reports->outstandingDues($request->user()->school));
    }
}
