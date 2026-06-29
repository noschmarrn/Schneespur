<?php

namespace App\Casts;

use App\Enums\JobType;
use App\ValueObjects\JobTypeValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<JobTypeValue|null, JobTypeValue|JobType|string|null>
 */
class JobTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?JobTypeValue
    {
        return $value === null ? null : new JobTypeValue((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value instanceof JobTypeValue) {
            $value = $value->value;
        } elseif ($value instanceof JobType) {
            $value = $value->value;
        }

        return [$key => $value];
    }
}
