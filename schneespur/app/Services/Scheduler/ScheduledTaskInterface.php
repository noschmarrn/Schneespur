<?php

namespace App\Services\Scheduler;

interface ScheduledTaskInterface
{
    public function slug(): string;

    public function label(): string;

    public function schedule(): string;

    public function handle(): void;

    public function isEnabled(): bool;

    public function source(): string;
}
