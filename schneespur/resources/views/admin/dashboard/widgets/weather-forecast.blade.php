@php
    $weather = $widget['data']['weather'];
    $weatherMissing = $widget['data']['weatherMissing'];
@endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.weather') }}</h3>

    @if ($weatherMissing)
        <div class="bg-blue-50 border-l-4 border-blue-400 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        {{ __('dashboard.weather_no_location') }}
                        <a href="{{ route('admin.settings.company') }}" class="font-medium underline hover:text-blue-600">{{ __('admin.nav_settings') }} &rarr;</a>
                    </p>
                </div>
            </div>
        </div>
    @elseif ($weather === null)
        <div class="bg-gray-50 border-l-4 border-gray-300 rounded-lg p-4">
            <p class="text-sm text-gray-600">{{ __('dashboard.weather_unavailable') }}</p>
        </div>
    @else
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="text-4xl font-bold text-gray-900">
                        {{ $weather['temperature'] !== null ? number_format($weather['temperature'], 1) . ' °C' : '—' }}
                    </div>
                    <div class="flex items-center space-x-2">
                        @include('admin.partials._weather-icon', ['icon' => $weather['icon']])
                        <span class="text-sm text-gray-600">{{ $weather['label'] }}</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <div class="text-xs font-medium text-gray-500">{{ __('dashboard.precipitation') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">{{ $weather['precipitation'] !== null ? number_format($weather['precipitation'], 1) . ' mm' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500">{{ __('dashboard.snow_depth') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">{{ $weather['snow_depth'] !== null ? number_format($weather['snow_depth'], 1) . ' cm' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500">{{ __('dashboard.wind_speed') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">{{ $weather['wind_speed'] !== null ? number_format($weather['wind_speed'], 1) . ' km/h' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500">{{ __('dashboard.humidity') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">{{ $weather['humidity'] !== null ? round($weather['humidity']) . ' %' : '—' }}</div>
                </div>
            </div>
        </div>
    @endif
</div>
