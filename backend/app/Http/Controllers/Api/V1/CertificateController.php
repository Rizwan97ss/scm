<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\IssueCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\CertificateService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CertificateController extends Controller
{
    private const WITH = ['student', 'certificateTemplate', 'issuedBy'];

    public function __construct(private readonly CertificateService $certificates) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Certificate::class);

        $paginator = QueryBuilder::for(Certificate::query()->visibleTo($request->user())->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('student_id'), AllowedFilter::exact('certificate_template_id'))
            ->allowedSorts('issued_date', 'created_at')
            ->defaultSort('-issued_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(CertificateResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $certificate = Certificate::query()->with(self::WITH)->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $certificate);

        return ApiResponse::success(new CertificateResource($certificate));
    }

    public function store(IssueCertificateRequest $request, CertificateTemplate $certificateTemplate): JsonResponse
    {
        $this->authorize('issue', $certificateTemplate);

        $certificate = $this->certificates->issue($certificateTemplate, $request->validated(), $request->user());

        return ApiResponse::created(new CertificateResource($certificate->load(self::WITH)));
    }

    public function pdf(Request $request, int $id): Response
    {
        $certificate = Certificate::query()->with(self::WITH)->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $certificate);

        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'schoolName' => $certificate->school->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        return $pdf->download(str($certificate->student->full_name.'-'.$certificate->certificate_number)->slug().'-certificate.pdf');
    }
}
