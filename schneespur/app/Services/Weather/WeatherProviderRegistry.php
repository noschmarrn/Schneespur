<?php

namespace App\Services\Weather;

use App\Models\Setting;
use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;

class WeatherProviderRegistry extends ExtensionRegistry
{
    public const DEFAULT_PROVIDER = 'openmeteo_free';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<WeatherProviderInterface> $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): WeatherProviderInterface
    {
        $slug ??= Setting::get('weather_provider', self::DEFAULT_PROVIDER);

        if (! $this->has($slug)) {
            $slug = self::DEFAULT_PROVIDER;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, array{name: string, requires_api_key: bool}> slug => provider info
     */
    public function availableProviders(): array
    {
        $result = [];
        foreach ($this->all() as $slug => $class) {
            $provider = $this->container->make($class);
            $result[$slug] = [
                'name' => $provider->name(),
                'requires_api_key' => $provider->requiresApiKey(),
            ];
        }

        return $result;
    }

    public function activeSlug(): string
    {
        $slug = Setting::get('weather_provider', self::DEFAULT_PROVIDER);

        return $this->has($slug) ? $slug : self::DEFAULT_PROVIDER;
    }
}
