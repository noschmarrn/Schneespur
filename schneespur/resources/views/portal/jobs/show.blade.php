@use('App\Services\Storage\StorageBackendRegistry')
<x-portal-layout>
    <div class="mb-4">
        <a href="{{ route('portal.jobs.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">&larr; {{ __('portal.job_back_to_list') }}</a>
    </div>

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('portal.job_detail_title') }}</h1>
        @if ($job->ended_at)
            <a href="{{ route('portal.jobs.pdf', $job) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('portal.job_detail_pdf_download') }}
            </a>
        @endif
    </div>

    {{-- Info Section --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_object') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $job->customerObject?->name ?? '–' }}
                    @if($job->customerObject?->street)
                        <span class="text-gray-500">— {{ $job->customerObject->street }}, {{ $job->customerObject->zip }} {{ $job->customerObject->city }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_type') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->type->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_started') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_ended') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->localEndedAt()?->format('d.m.Y H:i') ?? '–' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_duration') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->durationFormatted() }}</dd>
            </div>
            @if ($driverLastName)
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_driver') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $driverLastName }}</dd>
                </div>
            @endif
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">{{ __('portal.job_detail_notes') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->notes ?? __('portal.job_detail_notes_empty') }}</dd>
            </div>
        </dl>
    </div>

    {{-- Weather Section --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('portal.job_detail_weather') }}</h2>

        @if ($job->weatherSnapshots->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_moment') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_temperature') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_precipitation') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_snow_depth') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_weather_code') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($job->weatherSnapshots as $snapshot)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $snapshot->moment->label() }}</td>
                                @if ($snapshot->fetched_at)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->temperature }} &deg;C</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->precipitation }} mm</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->snow_depth }} cm</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->weather_code }}</td>
                                @else
                                    <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ __('job.weather_not_fetched') }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __('portal.job_detail_weather_empty') }}</p>
        @endif
    </div>

    {{-- Photos Section --}}
    @if ($customer->portal_show_photos && $job->jobPhotos->isNotEmpty())
        <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('portal.job_detail_photos') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach ($job->jobPhotos as $photo)
                    <a href="{{ app(StorageBackendRegistry::class)->urlWithFallback($photo->file_path) }}" target="_blank" class="group block aspect-square rounded-lg overflow-hidden bg-gray-100 ring-1 ring-gray-200 hover:ring-indigo-400 transition">
                        <img src="{{ app(StorageBackendRegistry::class)->urlWithFallback($photo->thumbnail_path) }}" alt="{{ $photo->caption ?? __('portal.job_detail_photo_alt') }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                    </a>
                @endforeach
            </div>
        </div>
    @elseif ($customer->portal_show_photos)
        <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('portal.job_detail_photos') }}</h2>
            <p class="text-sm text-gray-500">{{ __('portal.job_detail_photos_empty') }}</p>
        </div>
    @endif

    {{-- GPS Map Section --}}
    @if ($customer->portal_show_gps && $smoothedGps->isNotEmpty())
        <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('portal.job_detail_gps') }}</h2>
            <div
                id="map"
                class="h-96 rounded-lg"
                x-data
                x-init="
                    const points = {{ Js::from($smoothedGps) }};
                    const map = L.map($el).setView([points[0].lat, points[0].lon], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> contributors',
                        maxZoom: 19
                    }).addTo(map);
                    const latlngs = points.map(p => [p.lat, p.lon]);
                    const polyline = L.polyline(latlngs, { color: '#4f46e5', weight: 5, opacity: 0.7 }).addTo(map);
                    map.fitBounds(polyline.getBounds().pad(0.1));
                "
            ></div>
        </div>
    @elseif ($customer->portal_show_gps)
        <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('portal.job_detail_gps') }}</h2>
            <p class="text-sm text-gray-500">{{ __('portal.job_detail_gps_empty') }}</p>
        </div>
    @endif
</x-portal-layout>
