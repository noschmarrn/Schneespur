<?php

namespace App\Services\Extension;

use App\Models\Customer;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Customer-portal navigation. Unlike the admin NavigationRegistry this runs
 * against the `customer` guard (no Gates/permissions), so visibility is
 * controlled by an optional condition closure receiving the Customer.
 *
 * IMPORTANT: `label` stores a TRANSLATION KEY, not a translated string. The
 * portal locale is set per-request (per-customer) in EnsureCustomer, AFTER
 * boot, so the blade translates the key at render time via __($item['label']).
 */
class PortalNavigationRegistry extends ExtensionRegistry
{
    public function addItem(
        string $slug,
        string $label,
        string $route,
        int $order = 100,
        ?string $activePattern = null,
        ?Closure $condition = null,
    ): void {
        if ($this->has($slug)) {
            Log::warning("PortalNavigationRegistry: overwriting nav item '{$slug}'");
        }

        $this->items[$slug] = [
            'slug' => $slug,
            'label' => $label,
            'route' => $route,
            'order' => $order,
            'active_pattern' => $activePattern ?? $route,
            'condition' => $condition,
        ];
    }

    /**
     * @return array<int, array{slug: string, label: string, route: string, order: int, active_pattern: string, condition: Closure|null}>
     */
    public function getItems(?Customer $customer = null): array
    {
        $items = $this->items;

        if ($customer !== null) {
            $items = array_filter($items, function (array $item) use ($customer) {
                return $item['condition'] === null || ($item['condition'])($customer);
            });
        }

        usort($items, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values($items);
    }
}
