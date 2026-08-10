<?php

namespace App\Policies;

class RouteStopPolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'transport';
}
