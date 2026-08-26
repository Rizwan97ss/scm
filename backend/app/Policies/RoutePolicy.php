<?php

namespace App\Policies;

class RoutePolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'transport';
}
