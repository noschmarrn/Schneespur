<?php

namespace App\Services\Dispatch;

use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Collection;

class ManualDispatchStrategy implements DispatchStrategyInterface
{
    public function assign(Job $job, Collection $drivers): ?User
    {
        return null;
    }

    public function canHandle(Job $job): bool
    {
        return true;
    }

    public function label(): string
    {
        return __('dispatch.strategy_manual');
    }
}
