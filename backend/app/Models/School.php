<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDataColumn;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\TenantRun;
use Stancl\Tenancy\Events;

/**
 * The tenant model for stancl/tenancy, using "predefined columns" mode --
 * School's own real columns identify and describe it, not the package's
 * default JSON data blob. `data` (see the migration adding it) exists only
 * for the package's own internal bookkeeping and is never read/written by
 * application code. CentralConnection pins every School query to the
 * landlord connection regardless of which tenant is currently active --
 * without it, `School::query()` would silently follow whatever connection
 * tenancy last switched to, which is exactly the kind of bug that's easy to
 * introduce and hard to notice (see School::staffCount()/studentCount()).
 */
#[Fillable([
    'name', 'short_name', 'slug', 'email', 'phone', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'country', 'timezone', 'locale', 'is_active',
    'plan_id', 'billing_status', 'trial_ends_at',
])]
class School extends Model implements TenantWithDatabase
{
    use Billable, HasFactory, SoftDeletes;
    use CentralConnection, HasDatabase, HasDataColumn, HasInternalKeys, TenantRun;

    /**
     * Ties Eloquent's own model events to stancl/tenancy's tenant lifecycle
     * events — this is what makes TenancyServiceProvider's CreateDatabase/
     * MigrateDatabase job pipeline actually run whenever a School is
     * created anywhere (signup, seeders, the platform console). Without
     * this mapping, School::create() is just an ordinary Eloquent insert —
     * stancl's own base Tenant model wires the identical mapping; School
     * needs its own copy since it doesn't extend that class.
     *
     * Deliberately NOT mapping deleting/deleted to DeletingTenant/
     * TenantDeleted: School uses SoftDeletes, and Laravel fires those exact
     * same events for a routine soft-delete, not just forceDelete(). Wiring
     * them here would mean archiving a school silently and irreversibly
     * drops its entire tenant database via stancl's DeleteDatabase job —
     * the opposite of what soft-delete is for. Physically dropping a
     * tenant's database must stay a deliberate, explicit action, not an
     * automatic side effect of Eloquent's delete lifecycle.
     */
    protected $dispatchesEvents = [
        'saving' => Events\SavingTenant::class,
        'saved' => Events\TenantSaved::class,
        'creating' => Events\CreatingTenant::class,
        'created' => Events\TenantCreated::class,
        'updating' => Events\UpdatingTenant::class,
        'updated' => Events\TenantUpdated::class,
    ];

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey()
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * VirtualColumn (behind HasDataColumn) moves every attribute NOT listed
     * here into the `data` JSON blob on save — its own default is just
     * `['id']`. Every real column School's migrations actually create MUST
     * be listed, or it silently disappears from its real SQL column into
     * `data` on the next save, breaking every plain `where('slug', ...)`-
     * style query against it. This is the one thing "predefined columns"
     * mode requires you to get right by hand.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'uuid', 'name', 'short_name', 'slug', 'email', 'phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
            'country', 'timezone', 'locale', 'is_active', 'plan_id',
            'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
            'billing_status', 'created_at', 'updated_at', 'deleted_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            $school->uuid ??= (string) Str::uuid();
            $school->slug ??= Str::slug($school->short_name ?: $school->name);
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * This school's own frontend origin, derived from the central one
     * (config('app.frontend_url'), e.g. http://localtest.me:5173) by
     * inserting this school's slug as a subdomain -- there's no separate
     * per-school config value to read, subdomain identification means the
     * URL is always {slug}.{central host}.
     */
    public function frontendUrl(): string
    {
        $central = parse_url(config('app.frontend_url'));
        $port = isset($central['port']) ? ':'.$central['port'] : '';

        return "{$central['scheme']}://{$this->slug}.{$central['host']}{$port}";
    }

    /**
     * Students/staff live in this school's own tenant database, not a
     * queryable relation off the landlord-only School row — run() switches
     * into that database for the query and reverts afterward, safe to call
     * from a context where a different (or no) tenant is currently active,
     * e.g. the platform console listing usage across many schools at once.
     */
    public function studentCount(): int
    {
        return $this->run(fn () => Student::query()->count());
    }

    /**
     * "Staff seat" = a user holding any role except Student/Parent (see
     * docs/roadmap.md's Phase 6 notes). Queries model_has_roles directly
     * rather than the roles() relation for the same reason as above — one
     * tenant database per school means the table itself is already scoped,
     * no team/school filter needed (config('permission.teams') is false).
     */
    public function staffCount(): int
    {
        return $this->run(fn () => DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', User::class)
            ->whereNotIn('roles.name', ['Student', 'Parent'])
            ->distinct()
            ->count('model_has_roles.model_id'));
    }
}
