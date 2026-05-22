<?php

namespace App\Services\Module;

class DependencyValidator
{
    /**
     * @param  array  $manifest  The module.json manifest of the module being enabled
     * @param  array<string, array>  $activeModules  Slug => manifest of currently enabled modules
     * @return string[]  Array of error messages (empty = OK)
     */
    public function validate(array $manifest, array $activeModules): array
    {
        $errors = [];

        $requires = $manifest['requires'] ?? [];
        if (is_array($requires)) {
            foreach ($requires as $depSlug => $constraint) {
                if (! isset($activeModules[$depSlug])) {
                    $errors[] = "requires_missing:{$depSlug}:{$constraint}";
                    continue;
                }

                $depVersion = $activeModules[$depSlug]['version'] ?? '0.0.0';
                if (! $this->satisfiesConstraint($depVersion, $constraint)) {
                    $errors[] = "requires_version:{$depSlug}:{$constraint}:{$depVersion}";
                }
            }
        }

        $conflicts = $manifest['conflicts'] ?? [];
        if (is_array($conflicts)) {
            foreach ($conflicts as $conflictSlug) {
                if (isset($activeModules[$conflictSlug])) {
                    $errors[] = "conflict:{$conflictSlug}";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  string  $slug  The module being disabled
     * @param  array<string, array>  $allModules  Slug => manifest of all discovered modules
     * @param  array<string, array>  $activeModules  Slug => manifest of currently enabled modules
     * @return string[]  Slugs of active modules that depend on $slug
     */
    public function checkReverseDependencies(string $slug, array $allModules, array $activeModules): array
    {
        $dependants = [];

        foreach ($activeModules as $activeSlug => $manifest) {
            if ($activeSlug === $slug) {
                continue;
            }

            $requires = $manifest['requires'] ?? [];
            if (is_array($requires) && array_key_exists($slug, $requires)) {
                $dependants[] = $activeSlug;
            }
        }

        return $dependants;
    }

    public function satisfiesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        if (str_starts_with($constraint, '>=')) {
            $target = ltrim(substr($constraint, 2));
            return version_compare($version, $target, '>=');
        }

        if (str_starts_with($constraint, '^')) {
            $target = ltrim(substr($constraint, 1));
            $parts = explode('.', $target);
            $major = (int) ($parts[0] ?? 0);

            $nextMajor = ($major + 1) . '.0.0';

            return version_compare($version, $target, '>=')
                && version_compare($version, $nextMajor, '<');
        }

        if (str_starts_with($constraint, '~')) {
            $target = ltrim(substr($constraint, 1));
            $parts = explode('.', $target);
            $major = (int) ($parts[0] ?? 0);
            $minor = (int) ($parts[1] ?? 0);

            $nextMinor = $major . '.' . ($minor + 1) . '.0';

            return version_compare($version, $target, '>=')
                && version_compare($version, $nextMinor, '<');
        }

        return version_compare($version, $constraint, '>=');
    }
}
