<?php

namespace App\Models\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    protected ?array $cachedPermissions = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function assignRole(string|Role $role): void
    {
        $roleModel = $role instanceof Role
            ? $role
            : Role::where('slug', $role)->firstOrFail();

        $this->roles()->syncWithoutDetaching($roleModel->id);
        $this->flushPermissionCache();
    }

    public function removeRole(string|Role $role): void
    {
        $roleModel = $role instanceof Role
            ? $role
            : Role::where('slug', $role)->firstOrFail();

        $this->roles()->detach($roleModel->id);
        $this->flushPermissionCache();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->loadPermissions(), true);
    }

    public function loadPermissions(): array
    {
        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = Permission::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $this->roles()->pluck('roles.id')))
                ->pluck('slug')
                ->all();
        }

        return $this->cachedPermissions;
    }

    public function flushPermissionCache(): void
    {
        $this->cachedPermissions = null;
    }
}
