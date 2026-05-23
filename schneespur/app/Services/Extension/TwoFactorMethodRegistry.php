<?php

namespace App\Services\Extension;

use Illuminate\Contracts\Container\Container;

class TwoFactorMethodRegistry extends ExtensionRegistry
{
    /**
     * @param class-string<\App\Services\Auth\TwoFactorMethodInterface> $methodClass
     */
    public function registerMethod(string $slug, string $methodClass): void
    {
        $this->register($slug, $methodClass);
    }

    /**
     * @return array<string, class-string<\App\Services\Auth\TwoFactorMethodInterface>>
     */
    public function getMethods(): array
    {
        return $this->all();
    }

    /**
     * @return array<string, \App\Services\Auth\TwoFactorMethodInterface>
     */
    public function getAvailableMethods(Container $container): array
    {
        $available = [];
        foreach ($this->items as $slug => $methodClass) {
            if (class_exists($methodClass)) {
                $available[$slug] = $container->make($methodClass);
            }
        }

        return $available;
    }

    /**
     * @return array<string, class-string<\App\Services\Auth\TwoFactorMethodInterface>>
     */
    public function getByModule(string $module): array
    {
        return array_filter($this->items, function (string $class) use ($module) {
            return str_contains($class, "\\Module\\{$module}\\");
        });
    }

    public function removeByModule(string $module): void
    {
        foreach ($this->getByModule($module) as $slug => $class) {
            $this->remove($slug);
        }
    }
}
