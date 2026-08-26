<?php

namespace App\Policies;

class VisitorPolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'front-desk';
}
