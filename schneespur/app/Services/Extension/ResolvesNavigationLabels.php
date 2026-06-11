<?php

namespace App\Services\Extension;

/**
 * Shared lazy-label resolution for the navigation registries.
 *
 * Navigation is registered during boot() — before the app-wide default locale
 * and the per-user locale (EnsureDriver/EnsureCustomer) are applied. Storing the
 * translation KEY and resolving it here, at read time, lets a single registry
 * instance serve the correct language to every request. Resolving in the registry
 * (rather than each blade) means no consumer can reintroduce the boot-time freeze.
 */
trait ResolvesNavigationLabels
{
    private function resolveLabel(string $label): string
    {
        // The unlabelled 'top' group passes through; a non-key string (e.g. a
        // module that supplied a literal) falls back to itself via the translator.
        return $label === '' ? '' : (string) __($label);
    }
}
