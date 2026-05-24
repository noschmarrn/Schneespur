<?php

namespace App\Services\Dispatch;

use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Collection;

interface DispatchStrategyInterface
{
    /**
     * @param  Collection<int, User>  $drivers
     */
    public function assign(Job $job, Collection $drivers): ?User;

    public function canHandle(Job $job): bool;

    public function label(): string;
}
