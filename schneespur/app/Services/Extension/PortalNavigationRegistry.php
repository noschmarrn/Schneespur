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
 * boot, so getItems() resolves the key to the active locale at read time (see
 * ResolvesNavigationLabels). The blade renders the resolved label as-is.
 */
class PortalNavigationRegistry extends ExtensionRegistry
{
    use ResolvesNavigationLabels;

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

        foreach ($items as &$item) {
            $item['label'] = $this->resolveLabel($item['label']);
        }
        unset($item);

        return array_values($items);
    }
}
