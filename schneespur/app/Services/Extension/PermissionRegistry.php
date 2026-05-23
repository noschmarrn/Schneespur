<?php

namespace App\Services\Extension;

class PermissionRegistry extends ExtensionRegistry
{
    public function registerPermission(
        string $slug,
        string $label,
        string $group,
        ?string $module = null,
    ): void {
        $this->register($slug, [
            'slug' => $slug,
            'label' => $label,
            'group' => $group,
            'module' => $module,
        ]);
    }

    public function getPermissions(): array
    {
        return $this->items;
    }

    public function getByGroup(string $group): array
    {
        return array_filter($this->items, fn (array $p) => $p['group'] === $group);
    }

    public function getByModule(string $module): array
    {
        return array_filter($this->items, fn (array $p) => $p['module'] === $module);
    }

    public function removeByModule(string $module): void
    {
        foreach ($this->getByModule($module) as $slug => $entry) {
            $this->remove($slug);
        }
    }
}
