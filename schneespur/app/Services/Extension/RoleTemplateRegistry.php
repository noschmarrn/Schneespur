<?php

namespace App\Services\Extension;

class RoleTemplateRegistry extends ExtensionRegistry
{
    public function registerTemplate(
        string $slug,
        string $name,
        string $description,
        array $permissions,
        ?string $module = null,
    ): void {
        $this->register($slug, [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'permissions' => $permissions,
            'module' => $module,
        ]);
    }

    public function getTemplates(): array
    {
        return $this->items;
    }

    public function getByModule(string $module): array
    {
        return array_filter($this->items, fn (array $t) => $t['module'] === $module);
    }

    public function removeByModule(string $module): void
    {
        foreach ($this->getByModule($module) as $slug => $entry) {
            $this->remove($slug);
        }
    }
}
