<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Reads/writes database-driven settings. Settings now live in the active
 * tenant's own database (one row per key — see the `settings` tenant
 * migration's `unique(['key'])`), so there is no more global-default-vs-
 * school-override merge to do: whatever's in the current tenant connection
 * IS this school's settings. The now-vestigial $schoolId parameters are
 * kept only so unrelated call sites elsewhere (still passing a School id
 * for cache-key/legacy reasons) keep compiling; they no longer affect which
 * rows are read/written. Results are cached for the app's lifetime and
 * invalidated on write.
 */
class SettingsService
{
    private const CACHE_TTL = 3600;

    public function get(string $key, mixed $default = null, ?int $schoolId = null): mixed
    {
        return $this->allForSchool($schoolId)[$key] ?? $default;
    }

    public function set(string $key, mixed $value, ?int $schoolId = null, SettingType $type = SettingType::String, string $group = 'general', bool $isPublic = false): Setting
    {
        $setting = Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $type->serialize($value),
                'type' => $type->value,
                'group' => $group,
                'is_public' => $isPublic,
            ]
        );

        $this->forget($schoolId);

        return $setting;
    }

    /**
     * All settings for the current tenant.
     *
     * @return array<string, mixed>
     */
    public function allForSchool(?int $schoolId): array
    {
        return Cache::remember("settings:{$this->cacheKeyFor($schoolId)}", self::CACHE_TTL, function () {
            $merged = [];
            foreach (Setting::query()->get() as $setting) {
                $merged[$setting->key] = $setting->type->cast($setting->value);
            }

            return $merged;
        });
    }

    /**
     * Only settings flagged is_public, safe to expose to unauthenticated frontend boot.
     *
     * @return array<string, mixed>
     */
    public function publicForSchool(?int $schoolId): array
    {
        $publicKeys = Setting::query()
            ->where('is_public', true)
            ->pluck('key')
            ->unique();

        return collect($this->allForSchool($schoolId))
            ->only($publicKeys)
            ->all();
    }

    public function forget(?int $schoolId): void
    {
        Cache::forget("settings:{$this->cacheKeyFor($schoolId)}");
    }

    /**
     * $schoolId is accepted only for call-site backward compatibility and
     * never trusted for the actual key: several call sites (scheduled
     * commands looping over every School via $school->run(fn () => ...))
     * omit it, which used to collapse the key to a single shared
     * "settings:global" bucket on this app's un-tenant-tagged file cache
     * store (CacheTenancyBootstrapper is disabled — see config/tenancy.php)
     * — one tenant's settings would then leak into another's read for the
     * rest of the cache TTL. tenant()->id is always the authoritative
     * answer to "whose settings are these" per this class's own docblock,
     * so it's used unconditionally instead of the passed-in value.
     */
    private function cacheKeyFor(?int $schoolId): string
    {
        return (string) (tenant()?->id ?? $schoolId ?? 'central');
    }
}
