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

    private function cacheKeyFor(?int $schoolId): string
    {
        return $schoolId ?? 'global';
    }
}
