<?php

namespace App\Services\Extension;

use Illuminate\Support\Facades\Log;

abstract class ExtensionRegistry
{
    protected array $items = [];

    public function register(string $slug, mixed $entry): void
    {
        if (isset($this->items[$slug])) {
            Log::warning(static::class . ": overwriting existing registration '{$slug}'");
        }

        $this->items[$slug] = $entry;
    }

    public function resolve(string $slug): mixed
    {
        return $this->items[$slug] ?? null;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function has(string $slug): bool
    {
        return isset($this->items[$slug]);
    }

    public function remove(string $slug): void
    {
        unset($this->items[$slug]);
    }
}
