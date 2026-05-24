<?php

namespace Schneespur\Module\Example\Dispatch;

use App\Models\Job;
use App\Models\User;
use App\Services\Dispatch\DispatchStrategyInterface;
use Illuminate\Support\Collection;

class FirstAvailableStrategy implements DispatchStrategyInterface
{
    public function assign(Job $job, Collection $drivers): ?User
    {
        return $drivers->first();
    }

    public function canHandle(Job $job): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'First Available (Demo)';
    }
}
