<?php

namespace App\Services\Format;

use App\Services\Extension\ExtensionRegistry;

/**
 * Region templates that fill the format settings in one click. Core ships six;
 * a language-pack module registers its own the same way LocaleRegistry works,
 * so `lang-cs` can bring Czechia along without the core knowing about it.
 */
class RegionRegistry extends ExtensionRegistry
{
    public function add(RegionPreset $preset): void
    {
        $this->register($preset->code, $preset);
    }

    public function get(string $code): ?RegionPreset
    {
        return $this->items[$code] ?? null;
    }

    /** @return array<string, string> code => label, for the picker */
    public function labels(): array
    {
        return array_map(fn (RegionPreset $p) => $p->label, $this->items);
    }
}
