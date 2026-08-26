<?php

namespace App\Http\Resources;

use App\Models\StudentTransportAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentTransportAssignment */
class StudentTransportAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'full_name' => $this->student->full_name]),
            'route' => $this->whenLoaded('route', fn () => ['id' => $this->route->id, 'name' => $this->route->name]),
            'route_stop' => $this->whenLoaded('routeStop', fn () => ['id' => $this->routeStop->id, 'name' => $this->routeStop->name]),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? ['id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number] : null),
            'effective_from' => $this->effective_from?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
