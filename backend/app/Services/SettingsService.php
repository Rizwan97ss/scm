<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Reads/writes database-driven settings. A setting with school_id = null is the
 * global default; a school-specific row (same key) overrides it for that school.
 * Results are cached per school for the app's lifetime and invalidated on write.
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
            ['school_id' => $schoolId, 'key' => $key],
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
     * All effective settings for a school: global defaults overridden by school-specific values.
     *
     * @return array<string, mixed>
     */
    public function allForSchool(?int $schoolId): array
    {
        return Cache::remember("settings:{$this->cacheKeyFor($schoolId)}", self::CACHE_TTL, function () use ($schoolId) {
            $global = Setting::query()->whereNull('school_id')->get();
            $scoped = $schoolId ? Setting::query()->where('school_id', $schoolId)->get() : collect();

            $merged = [];
            foreach ($global as $setting) {
                $merged[$setting->key] = $setting->type->cast($setting->value);
            }
            foreach ($scoped as $setting) {
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
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
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
