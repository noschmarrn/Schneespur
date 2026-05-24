<x-driver-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('driver.jobs.index') }}" class="min-h-[44px] min-w-[44px] flex items-center justify-center text-gray-400 hover:text-gray-200 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-100">{{ __('driver.history_detail_title') }}</h1>
        </div>

        {{-- Customer & type --}}
        <div class="bg-gray-800 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">{{ $job->localStartedAt()->format('d.m.Y') }}</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-900 text-blue-300">
                    {{ $job->type->label() }}
                </span>
            </div>

            <div>
                <p class="text-xs text-gray-500">{{ __('driver.history_detail_customer') }}</p>
                <p class="text-base font-semibold text-gray-100">{{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '–' }}</p>
            </div>

            @if($job->customerObject)
                <div>
                    <p class="text-xs text-gray-500">{{ __('driver.dash_object') }}</p>
                    <p class="text-sm text-gray-200">{{ $job->customerObject->name }}</p>
                </div>
            @endif

            @if($job->customerObject?->street)
                <div>
                    <p class="text-xs text-gray-500">{{ __('driver.history_detail_address') }}</p>
                    <p class="text-sm text-gray-300">{{ $job->customerObject->street }}, {{ $job->customerObject->zip }} {{ $job->customerObject->city }}</p>
                </div>
            @endif
        </div>

        {{-- Vehicle --}}
        @if($job->vehicle)
            <div class="bg-gray-800 rounded-xl p-4">
                <p class="text-xs text-gray-500">{{ __('job.field_vehicle') }}</p>
                <p class="text-sm font-medium text-gray-100">{{ $job->vehicle->displayLabel() }}</p>
            </div>
        @endif

        {{-- Times & duration --}}
        <div class="bg-gray-800 rounded-xl p-4 space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500">{{ __('driver.history_detail_started') }}</p>
                    <p class="text-sm text-gray-100">{{ $job->localStartedAt()->format('H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('driver.history_detail_ended') }}</p>
                    <p class="text-sm text-gray-100">
                        @if($job->ended_at)
                            {{ $job->localEndedAt()->format('H:i') }}
                        @else
                            <span class="text-green-400">{{ __('driver.history_duration_active') }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('driver.history_detail_duration') }}</p>
                <p class="text-sm text-gray-100">
                    @if($job->ended_at)
                        {{ $job->durationFormatted() }}
                    @else
                        <span class="text-green-400">{{ __('driver.history_duration_active') }}</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Notes --}}
        <div class="bg-gray-800 rounded-xl p-4 space-y-2">
            <p class="text-xs text-gray-500">{{ __('driver.history_detail_notes') }}</p>
            <p class="text-sm text-gray-300">{{ $job->notes ?: __('driver.history_detail_no_notes') }}</p>
        </div>

        {{-- Weather --}}
        @if($job->weatherSnapshots->isNotEmpty())
            <div class="bg-gray-800 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-gray-100">{{ __('driver.history_detail_weather') }}</p>

                @foreach($job->weatherSnapshots->sortBy('moment') as $ws)
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-gray-400">
                            {{ $ws->moment === \App\Enums\WeatherMoment::Start ? __('driver.history_detail_weather_start') : __('driver.history_detail_weather_end') }}
                        </p>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-gray-700 rounded-lg p-2">
                                <p class="text-xs text-gray-500">{{ __('driver.history_detail_temperature') }}</p>
                                <p class="text-sm font-medium text-gray-100">{{ number_format($ws->temperature, 1) }} °C</p>
                            </div>
                            <div class="bg-gray-700 rounded-lg p-2">
                                <p class="text-xs text-gray-500">{{ __('driver.history_detail_precipitation') }}</p>
                                <p class="text-sm font-medium text-gray-100">{{ number_format($ws->precipitation, 1) }} mm</p>
                            </div>
                            <div class="bg-gray-700 rounded-lg p-2">
                                <p class="text-xs text-gray-500">{{ __('driver.history_detail_snow_depth') }}</p>
                                <p class="text-sm font-medium text-gray-100">{{ number_format($ws->snow_depth, 1) }} cm</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Photos --}}
        @if($job->jobPhotos->isNotEmpty())
            <div class="bg-gray-800 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-gray-100">{{ __('driver.history_detail_photos') }} ({{ $job->jobPhotos->count() }})</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($job->jobPhotos->sortBy('sort_order') as $photo)
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-700">
                            <img src="{{ app(\App\Services\Storage\StorageBackendRegistry::class)->urlWithFallback($photo->thumbnail_path ?: $photo->file_path) }}"
                                 alt="{{ $photo->caption ?: __('driver.dash_photo_alt') }}"
                                 class="w-full h-full object-cover">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- GPS points --}}
        @if($job->gps_points_count > 0)
            <div class="bg-gray-800 rounded-xl p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div>
                    <p class="text-xs text-gray-500">{{ __('driver.history_detail_gps_points') }}</p>
                    <p class="text-sm font-medium text-gray-100">{{ $job->gps_points_count }}</p>
                </div>
            </div>
        @endif
    </div>
</x-driver-layout>
