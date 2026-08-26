<?php

namespace App\Policies;

class HostelRoomPolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'hostel';
}
