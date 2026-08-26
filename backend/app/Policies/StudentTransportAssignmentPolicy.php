<?php

namespace App\Policies;

class StudentTransportAssignmentPolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'transport';
}
