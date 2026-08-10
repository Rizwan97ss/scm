<?php

namespace App\Policies;

class BookIssuePolicy extends BaseViewManagePolicy
{
    protected string $permissionPrefix = 'library';
}
