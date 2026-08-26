<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array SubjectResultService::forGroup() already returns —
 * that array shape is also embedded directly (not through this resource)
 * inside ExamService::reportCard()'s "subjects" list, so both places stay
 * byte-for-byte identical without a second implementation to drift.
 *
 * @mixin array
 */
class SubjectResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
