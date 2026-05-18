<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Weather\WeatherProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherSettingsController extends Controller
{
    public function edit(WeatherProviderRegistry $registry): View
    {
        return view('admin.settings.weather', [
            'providers' => $registry->availableProviders(),
            'activeProvider' => $registry->activeSlug(),
            'apiKey' => Setting::get('weather_api_key', ''),
            'userAgentEmail' => Setting::get('weather_user_agent_email', ''),
            'cacheTtlMinutes' => (int) (Setting::get('weather_cache_ttl', 300) / 60),
        ]);
    }

    public function update(Request $request, WeatherProviderRegistry $registry): RedirectResponse
    {
        $providerSlugs = array_keys($registry->availableProviders());

        $validated = $request->validate([
            'weather_provider' => ['required', 'string', 'in:'.implode(',', $providerSlugs)],
            'weather_api_key' => ['nullable', 'string', 'max:255'],
            'weather_user_agent_email' => ['nullable', 'email', 'max:255'],
            'weather_cache_ttl' => ['required', 'integer', 'min:1'],
        ]);

        Setting::set('weather_provider', $validated['weather_provider']);
        Setting::set('weather_api_key', $validated['weather_api_key'] ?? '');
        Setting::set('weather_user_agent_email', $validated['weather_user_agent_email'] ?? '');
        Setting::set('weather_cache_ttl', $validated['weather_cache_ttl'] * 60, 'int');

        return redirect()->route('admin.settings.weather')
            ->with('success', __('weather.settings_saved'));
    }

    public function testConnection(Request $request, WeatherProviderRegistry $registry): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
        ]);

        $slug = $request->input('provider');

        if (! $registry->has($slug)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unknown provider',
                'latency_ms' => 0,
            ]);
        }

        $provider = $registry->resolve($slug);

        $lat = (float) Setting::get('company_lat', 48.1351);
        $lon = (float) Setting::get('company_lon', 11.5820);

        $result = $provider->testConnection($lat, $lon);

        return response()->json($result);
    }
}
