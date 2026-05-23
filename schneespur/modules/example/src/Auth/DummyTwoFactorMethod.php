<?php

namespace Schneespur\Module\Example\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorMethodInterface;

class DummyTwoFactorMethod implements TwoFactorMethodInterface
{
    private static array $enabled = [];

    public function slug(): string
    {
        return 'dummy-2fa';
    }

    public function name(): string
    {
        return 'Dummy 2FA';
    }

    public function enable(User $user): void
    {
        self::$enabled[$user->id] = true;
    }

    public function disable(User $user): void
    {
        unset(self::$enabled[$user->id]);
    }

    public function verify(User $user, string $code): bool
    {
        return true;
    }

    public function isEnabled(User $user): bool
    {
        return self::$enabled[$user->id] ?? false;
    }

    public static function resetState(): void
    {
        self::$enabled = [];
    }
}
