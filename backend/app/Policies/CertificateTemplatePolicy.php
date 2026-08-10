<?php

namespace App\Policies;

use App\Models\CertificateTemplate;
use App\Models\User;

class CertificateTemplatePolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'certificates';

    public function issue(User $user, CertificateTemplate $certificateTemplate): bool
    {
        return $user->can('certificates.issue');
    }
}
