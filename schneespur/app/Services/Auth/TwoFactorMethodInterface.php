<?php

namespace App\Services\Auth;

use App\Models\User;

interface TwoFactorMethodInterface
{
    public function slug(): string;

    public function name(): string;

    public function enable(User $user): void;

    public function disable(User $user): void;

    public function verify(User $user, string $code): bool;

    public function isEnabled(User $user): bool;
}
