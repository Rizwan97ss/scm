<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
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

        return ApiResponse::success($this->settings->allForSchool(tenant()?->id));
    }

    /**
     * Unauthenticated-safe branding/localization values the login screen
     * needs before a session exists. Runs on the tenant route group, so
     * tenancy.subdomain middleware has already resolved which school this
     * is from the Host header — no ?school=<slug> query param needed
     * anymore. The central/signup domain doesn't call this at all: per the
     * original plan, public/marketing pages use static platform branding,
     * not a per-school lookup (there's no school yet at that point anyway).
     */
    public function public(Request $request): JsonResponse
    {
        return ApiResponse::success($this->settings->publicForSchool(tenant()?->id));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        // Super Admin (PlatformUser) can't reach this tenant-scoped route at
        // all, so there's no "global" (cross-tenant default) toggle to
        // support here anymore -- every write here is this tenant's own
        // override, by construction. SettingsService's $schoolId param is
        // vestigial now (see its own docblock) but still required by its
        // signature, so tenant()?->id is passed through unused.
        foreach ($request->array('settings') as $setting) {
            $this->settings->set(
                $setting['key'],
                $setting['value'] ?? null,
                tenant()?->id,
                SettingType::from($setting['type']),
                $setting['group'],
                $setting['is_public'] ?? false,
            );
        }

        return ApiResponse::success($this->settings->allForSchool(tenant()?->id), 'Settings updated.');
    }
}
