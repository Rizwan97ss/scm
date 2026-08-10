<?php

namespace App\Models\Platform;

use Database\Factories\Platform\PlatformUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Super Admin, split out from the tenant-scoped User model (Sub-phase D) --
 * a platform operator isn't "inside" any one school, so it can't live in a
 * tenant database the way School Admins/Teachers/Students do. Authenticates
 * on the separate 'platform' guard (config/auth.php), landlord-connection
 * only (CentralConnection), no Spatie roles/permissions: being a
 * PlatformUser at all already implies full cross-tenant access (see
 * AppServiceProvider's Gate::before), the way Super Admin's blanket bypass
 * always worked, just no longer modeled as "a User with school_id === null."
 */
#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class PlatformUser extends Authenticatable
{
    use CentralConnection, HasFactory, Notifiable, SoftDeletes;

    /** @use HasFactory<PlatformUserFactory> */
    protected static function newFactory(): PlatformUserFactory
    {
        return PlatformUserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PlatformUser $user) {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
