<?php

namespace App\ValueObjects;

use App\Services\Extension\JobTypeRegistry;

final class JobTypeValue
{
    public function __construct(public readonly string $value) {}

    public function label(): string
    {
        return app(JobTypeRegistry::class)->label($this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
