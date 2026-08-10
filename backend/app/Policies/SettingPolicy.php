<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->can('settings.view');
    }

    public function update(User $user, ?Setting $setting = null): bool
    {
        return $user->can('settings.edit');
    }
}
