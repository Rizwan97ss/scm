<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Models\School;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::success($this->settings->allForSchool($request->user()->school_id));
    }

    /**
     * Unauthenticated-safe branding/localization values the login screen and
     * public pages need before a session exists. Resolves the school via
     * ?school=<slug>; defaults to global settings if omitted (single-school
     * deployments never need the param).
     */
    public function public(Request $request): JsonResponse
    {
        $schoolId = null;

        if ($request->filled('school')) {
            $schoolId = School::query()->where('slug', $request->string('school')->toString())->value('id');
        }

        return ApiResponse::success($this->settings->publicForSchool($schoolId));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $actor = $request->user();
        $schoolId = $actor->school_id === null && $request->boolean('global') ? null : $actor->school_id;

        foreach ($request->array('settings') as $setting) {
            $this->settings->set(
                $setting['key'],
                $setting['value'] ?? null,
                $schoolId,
                SettingType::from($setting['type']),
                $setting['group'],
                $setting['is_public'] ?? false,
            );
        }

        return ApiResponse::success($this->settings->allForSchool($schoolId), 'Settings updated.');
    }
}
