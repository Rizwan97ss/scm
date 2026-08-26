<?php

namespace App\Policies;

class BookPolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'library';
}
