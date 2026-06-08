<?php

namespace App\Services\Extension;

/**
 * Registry of available UI locales. Core registers 'de' and 'en' at boot;
 * language-pack modules register additional codes (e.g. 'cs') during
 * ModuleManager::boot(). Replaces the previously hardcoded ['de','en']
 * allow-lists across the app.
 */
class LocaleRegistry extends ExtensionRegistry
{
    public function add(string $code, string $label): void
    {
        $this->register($code, $label); // base handles dup-warning
    }

    /** @return array<int, string> */
    public function codes(): array
    {
        return array_keys($this->items);
    }

    /** @return array<string, string> code => label, for pickers */
    public function labels(): array
    {
        return $this->items;
    }
}
