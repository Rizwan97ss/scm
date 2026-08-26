<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'admission_number' => $this->admission_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender?->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'blood_group' => $this->blood_group,
            'nationality' => $this->nationality,
            'academic_year_id' => $this->academic_year_id,
            'current_grade_level_id' => $this->current_grade_level_id,
            'grade_level' => $this->whenLoaded('currentGradeLevel', fn () => $this->currentGradeLevel ? ['id' => $this->currentGradeLevel->id, 'name' => $this->currentGradeLevel->name] : null),
            'current_section_id' => $this->current_section_id,
            'section' => $this->whenLoaded('currentSection', fn () => $this->currentSection ? ['id' => $this->currentSection->id, 'name' => $this->currentSection->name] : null),
            'current_department_id' => $this->current_department_id,
            'department' => $this->whenLoaded('currentDepartment', fn () => $this->currentDepartment ? ['id' => $this->currentDepartment->id, 'name' => $this->currentDepartment->name] : null),
            'roll_number' => $this->roll_number,
            'admission_date' => $this->admission_date?->toDateString(),
            'status' => $this->status?->value,
            'previous_school_name' => $this->previous_school_name,
            'previous_school_details' => $this->previous_school_details,
            'medical_info' => $this->medical_info,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'photo_url' => $this->getFirstMedia('photo') ? route('api.v1.media.show', $this->getFirstMedia('photo')) : null,
            'guardians' => $this->whenLoaded('guardians', fn () => $this->guardians->map(fn ($guardian) => [
                'id' => $guardian->id,
                'full_name' => $guardian->full_name,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
                'relationship_type' => $guardian->pivot->relationship_type?->value,
                'is_primary' => $guardian->pivot->is_primary,
                'can_pickup' => $guardian->pivot->can_pickup,
            ])),
            'documents' => $this->when($request->routeIs('*.students.show'), fn () => collect(Student::DOCUMENT_COLLECTIONS)
                ->flatMap(fn ($collection) => $this->getMedia($collection)->map(fn ($media) => [
                    'id' => $media->id,
                    'collection' => $collection,
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                    'url' => route('api.v1.media.show', $media),
                    'created_at' => $media->created_at?->toIso8601String(),
                ]))->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
