<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\IssueCertificateRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\CertificateService;
use App\Support\ApiResponse;
use App\Support\QrCodeGenerator;
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

    /** layout key (CertificateTemplate::layout) => Blade view. Keep in sync with StoreCertificateTemplateRequest's Rule::in() list. */
    private const LAYOUT_VIEWS = [
        'classic' => 'pdf.certificates.classic',
        'recognition' => 'pdf.certificates.recognition',
        'achievement' => 'pdf.certificates.achievement',
        'merit' => 'pdf.certificates.merit',
    ];

    /** Layouts that print a QR code linking to the public verify page — the plainer `classic`/`recognition` designs skip it. */
    private const LAYOUTS_WITH_QR = ['achievement', 'merit'];

    public function pdf(Request $request, int $id): Response
    {
        $certificate = Certificate::query()->with(self::WITH)->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $certificate);

        $layout = $certificate->certificateTemplate->layout;
        $view = self::LAYOUT_VIEWS[$layout] ?? self::LAYOUT_VIEWS['classic'];

        $pdf = Pdf::loadView($view, [
            'certificate' => $certificate,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
            'signatories' => $certificate->certificateTemplate->signatories ?? [],
            'qrCodeDataUri' => in_array($layout, self::LAYOUTS_WITH_QR, true)
                ? QrCodeGenerator::dataUri($this->verificationUrl($certificate))
                : null,
        ]);

        return $pdf->download(str($certificate->student->full_name.'-'.$certificate->certificate_number)->slug().'-certificate.pdf');
    }

    /** Public, unauthenticated — see routes/api.php's "Public within this tenant" section. What a scanned QR code (or a manually-typed verify link) resolves to. */
    public function verify(string $token): JsonResponse
    {
        $certificate = Certificate::query()->with(['student', 'certificateTemplate'])->where('verification_token', $token)->first();

        if (! $certificate) {
            return ApiResponse::success(['valid' => false], status: 404);
        }

        return ApiResponse::success([
            'valid' => true,
            'certificate_number' => $certificate->certificate_number,
            'student_name' => $certificate->student->full_name,
            'template_name' => $certificate->certificateTemplate->name,
            'issued_date' => $certificate->issued_date->toDateString(),
            'school_name' => tenant()->name,
        ]);
    }

    private function verificationUrl(Certificate $certificate): string
    {
        return tenant()->frontendUrl()."/verify-certificate/{$certificate->verification_token}";
    }
}
