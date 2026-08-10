<?php

namespace App\Policies;

class AnnouncementPolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'communication';
}
