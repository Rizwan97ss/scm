<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

/**
 * Reading the published notice board is unpermissioned for every
 * authenticated user — same self-service shape as homework submission or
 * self check-in. notice-board.view only ever expands reach to *drafts*
 * (see Notice::scopeVisibleTo()); it never gates the endpoint itself.
 */
class NoticePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notice $notice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('notice-board.create');
    }

    public function update(User $user, Notice $notice): bool
    {
        return $user->can('notice-board.edit');
    }

    public function delete(User $user, Notice $notice): bool
    {
        return $user->can('notice-board.delete');
    }

    public function publish(User $user, Notice $notice): bool
    {
        return $user->can('notice-board.publish');
    }
}
