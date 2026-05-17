<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function update(User $user, Job $job): bool
    {
        return $user->isAdmin() && $job->isInGracePeriod();
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->isAdmin();
    }

    public function viewAudit(User $user, Job $job): bool
    {
        return $user->isAdmin();
    }
}
