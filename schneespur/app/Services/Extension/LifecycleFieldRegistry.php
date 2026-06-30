<?php

namespace App\Services\Extension;

use App\Enums\LifecyclePoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

class LifecycleFieldRegistry extends ExtensionRegistry
{
    public function registerField(LifecyclePoint $point, string $slug, array $contribution): void
    {
        $this->register($slug, array_merge([
            'slug' => $slug,
            'point' => $point,
            'view' => null,
            'rules' => [],
            'persist' => null,
            'order' => 100,
            'permission' => null,
        ], $contribution, ['slug' => $slug, 'point' => $point]));
    }

    /**
     * Ordered, permission-filtered contributions for a point.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contributions(LifecyclePoint $point, ?Authenticatable $user = null): array
    {
        $entries = array_filter(
            $this->items,
            fn (array $e) => $e['point'] === $point
                && ($e['permission'] === null || $user === null || Gate::forUser($user)->allows($e['permission'])),
        );

        $entries = array_values($entries);
        usort($entries, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $entries;
    }

    /**
     * Merged validation rules for a point.
     *
     * @return array<string, mixed>
     */
    public function rules(LifecyclePoint $point, ?Authenticatable $user = null): array
    {
        $rules = [];

        foreach ($this->contributions($point, $user) as $entry) {
            $rules = array_merge($rules, $entry['rules']);
        }

        return $rules;
    }

    /** @return string[] */
    public function fieldKeys(LifecyclePoint $point): array
    {
        return array_keys($this->rules($point));
    }

    public function render(LifecyclePoint $point, ?Authenticatable $user = null): string
    {
        $html = '';

        foreach ($this->contributions($point, $user) as $entry) {
            if ($entry['view'] === null) {
                continue;
            }

            $html .= View::make($entry['view'], ['user' => $user])->render();
        }

        return $html;
    }
}
