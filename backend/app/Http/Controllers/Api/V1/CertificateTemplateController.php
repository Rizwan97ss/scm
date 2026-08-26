<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Certificates\StoreCertificateTemplateRequest;
use App\Http\Requests\Certificates\UpdateCertificateTemplateRequest;
use App\Http\Resources\CertificateTemplateResource;
use App\Models\CertificateTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CertificateTemplateController extends CrudController
{
    protected array $allowedFilters = ['name', 'type', 'is_active'];

    protected array $allowedSorts = ['name', 'created_at'];

    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return CertificateTemplate::class;
    }

    protected function resourceClass(): string
    {
        return CertificateTemplateResource::class;
    }

    public function store(StoreCertificateTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', CertificateTemplate::class);

        $template = CertificateTemplate::query()->create($request->validated());

        return ApiResponse::created(new CertificateTemplateResource($template));
    }

    public function update(UpdateCertificateTemplateRequest $request, CertificateTemplate $certificateTemplate): JsonResponse
    {
        $this->authorize('update', $certificateTemplate);

        $certificateTemplate->update($request->validated());

        return ApiResponse::success(new CertificateTemplateResource($certificateTemplate));
    }
}
