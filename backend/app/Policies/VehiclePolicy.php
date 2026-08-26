<?php

namespace App\Policies;

class VehiclePolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'transport';
}
