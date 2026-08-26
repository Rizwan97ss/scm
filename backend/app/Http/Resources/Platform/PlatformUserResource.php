<?php

namespace App\Http\Resources\Platform;

use App\Models\Platform\PlatformUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlatformUser */
class PlatformUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'mfa_enabled' => $this->hasMfaConfirmed(),
            'mfa_grace_period_ends_at' => $this->mfa_grace_period_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
