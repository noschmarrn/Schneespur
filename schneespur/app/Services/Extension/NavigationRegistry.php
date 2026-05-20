<?php

namespace App\Services\Extension;

use Illuminate\Support\Facades\Log;

class NavigationRegistry extends ExtensionRegistry
{
    protected array $groups = [];

    public function __construct(
        private readonly FilterRegistry $filterRegistry,
    ) {}

    public function addGroup(string $key, string $label, int $order = 100): void
    {
        $this->groups[$key] = ['key' => $key, 'label' => $label, 'order' => $order];
    }

    public function getGroups(): array
    {
        $groups = $this->groups;
        usort($groups, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $groups;
    }

    public function addItem(
        string $group,
        string $slug,
        string $label,
        string $route,
        string $icon,
        int $order = 100,
        ?string $permission = null,
        ?string $routeCheck = null,
        ?string $activePattern = null,
        ?string $badge = null,
    ): void {
        if ($this->has($slug)) {
            Log::warning("NavigationRegistry: overwriting nav item '{$slug}'");
        }

        $this->items[$slug] = [
            'group' => $group,
            'slug' => $slug,
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'order' => $order,
            'permission' => $permission,
            'route_check' => $routeCheck,
            'active_pattern' => $activePattern ?? $route,
            'badge' => $badge,
        ];
    }

    /**
     * @return array<string, array<int, array{slug: string, label: string, route: string, icon: string, order: int, permission: string|null}>>
     */
    public function getItems(?string $userPermission = null): array
    {
        $items = $this->items;

        if ($userPermission !== null) {
            $items = array_filter($items, function (array $item) use ($userPermission) {
                return $item['permission'] === null || $item['permission'] === $userPermission;
            });
        }

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['group']][] = $item;
        }

        foreach ($grouped as &$groupItems) {
            usort($groupItems, fn (array $a, array $b) => $a['order'] <=> $b['order']);
        }

        return $this->filterRegistry->apply('schneespur.navigation.items', $grouped);
    }
}
