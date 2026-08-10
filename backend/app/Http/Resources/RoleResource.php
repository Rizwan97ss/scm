<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_system' => $this->name === 'Super Admin',
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
        ];
    }
}
