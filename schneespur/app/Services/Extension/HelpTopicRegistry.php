<?php

namespace App\Services\Extension;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class HelpTopicRegistry extends ExtensionRegistry
{
    /**
     * @param  array<string,string>|string  $title      ['de'=>…,'en'=>…] or a translation key
     * @param  string  $view                             module blade path, e.g. 'lager::help.index'
     * @param  array<string,string>|string|null  $description
     */
    public function registerTopic(
        string $slug,
        array|string $title,
        string $view,
        array|string|null $description = null,
        string $icon = 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M12 18.75h.007v.008H12v-.008z',
        ?string $permission = null,
    ): void {
        $this->items[$slug] = [
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'view' => $view,
            'icon' => $icon,
            'permission' => $permission,
        ];
    }

    /**
     * @return array<string, array{slug:string,title:string,description:string,icon:string,view:string}>
     */
    public function getTopics(?Authenticatable $user = null): array
    {
        $out = [];
        foreach ($this->items as $slug => $topic) {
            if ($topic['permission'] !== null && $user !== null
                && ! Gate::forUser($user)->allows($topic['permission'])) {
                continue;
            }

            $out[$slug] = [
                'slug' => $slug,
                'title' => $this->resolveLabel($topic['title']),
                'description' => $this->resolveLabel($topic['description'] ?? ''),
                'icon' => $topic['icon'],
                'view' => $topic['view'],
            ];
        }

        return $out;
    }

    private function resolveLabel(array|string|null $value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? reset($value) ?: '');
        }

        return ($value === null || $value === '') ? '' : (string) __($value);
    }
}
