<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobAudit;

class JobAuditService
{
    public function logChange(Job $job, string $action, array $oldValues = [], array $newValues = []): JobAudit
    {
        return JobAudit::create([
            'job_id' => $job->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()->ip(),
        ]);
    }

    public function logDeletion(Job $job): JobAudit
    {
        return $this->logChange($job, 'deleted', $job->attributesToArray());
    }
}
