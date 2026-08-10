<?php

namespace App\Http\Resources;

use App\Models\RouteStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RouteStop */
class RouteStopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'route_id' => $this->route_id,
            'name' => $this->name,
            'sequence' => $this->sequence,
        ];
    }
}
