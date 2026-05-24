<?php

namespace App\Services\Extension;

use App\Models\Setting;
use App\Services\Dispatch\DispatchStrategyInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class DispatchStrategyRegistry extends ExtensionRegistry
{
    public const DEFAULT_STRATEGY = 'manual';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string<DispatchStrategyInterface>  $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): DispatchStrategyInterface
    {
        $slug ??= Setting::get('dispatch_strategy', self::DEFAULT_STRATEGY);

        if (! $this->has($slug)) {
            Log::warning("DispatchStrategyRegistry: configured strategy '{$slug}' not found, falling back to 'manual'");
            $slug = self::DEFAULT_STRATEGY;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, array{name: string}>
     */
    public function availableStrategies(): array
    {
        $result = [];
        foreach ($this->all() as $slug => $class) {
            $strategy = $this->container->make($class);
            $result[$slug] = [
                'name' => $strategy->label(),
            ];
        }

        return $result;
    }

    public function activeSlug(): string
    {
        $slug = Setting::get('dispatch_strategy', self::DEFAULT_STRATEGY);

        return $this->has($slug) ? $slug : self::DEFAULT_STRATEGY;
    }
}
