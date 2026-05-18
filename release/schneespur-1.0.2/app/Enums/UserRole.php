<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Driver = 'driver';

    /**
     * Return the human-readable label for this role.
     * Uses the lang/de/admin.php (or current locale equivalent) keys.
     */
    public function label(): string
    {
        return __('admin.role_' . $this->value);
    }
}
