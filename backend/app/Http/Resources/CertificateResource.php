<?php

namespace App\Http\Resources;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Certificate */
class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'full_name' => $this->student->full_name]),
            'certificate_template' => $this->whenLoaded('certificateTemplate', fn () => ['id' => $this->certificateTemplate->id, 'name' => $this->certificateTemplate->name, 'type' => $this->certificateTemplate->type]),
            'certificate_number' => $this->certificate_number,
            'issued_date' => $this->issued_date?->toDateString(),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => ['id' => $this->issuedBy->id, 'full_name' => $this->issuedBy->full_name]),
            'content' => $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
