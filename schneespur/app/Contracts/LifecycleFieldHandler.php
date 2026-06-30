<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface LifecycleFieldHandler
{
    public function handle(Model $entity, array $validated, User $user): void;
}
