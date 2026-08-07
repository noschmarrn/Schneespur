<?php

namespace App\Services\Notification;

use App\Models\Job;
use App\Services\Extension\ExtensionRegistry;
use Illuminate\Mail\Mailable;

/**
 * Which Mailable belongs to which notification type.
 *
 * A factory, not a class-string: JobCompletedMail takes five constructor
 * arguments out of the $context. Instantiating from a bare class name would
 * force this registry to know every Mailable's context shape — and that is
 * exactly what makes it un-extensible.
 *
 * The method names avoid register()/resolve() on purpose: ExtensionRegistry
 * already declares both with different signatures, and overriding those with
 * an incompatible one is a PHP fatal.
 */
class MailableRegistry extends ExtensionRegistry
{
    /** @param callable(Job, array<string, mixed>): ?Mailable $factory */
    public function registerType(string $type, callable $factory): void
    {
        $this->register($type, $factory);
    }

    /** @param array<string, mixed> $context */
    public function make(string $type, Job $job, array $context): ?Mailable
    {
        $factory = $this->resolve($type);

        return $factory === null ? null : $factory($job, $context);
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys($this->all());
    }
}
