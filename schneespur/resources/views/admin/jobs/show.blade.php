@use('App\Services\Storage\StorageBackendRegistry')
<x-admin-layout>
    <x-slot name="header">
        <a href="{{ route('admin.jobs.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; {{ __('job.page_list') }}</a>
        <span class="mx-2 text-gray-400">/</span>
        {{ __('job.page_detail') }} <x-help-icon topic="jobs" />
    </x-slot>

    <x-page-header :title="__('job.page_detail')">
        <x-slot name="action">
            <div class="flex items-center gap-2">
                @can('update', $job)
                    <a href="{{ route('admin.jobs.edit', $job) }}" class="inline-flex items-center gap-2 rounded-md bg-amber-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ __('job.edit_title') }}
                    </a>
                @endcan
                @can('delete', $job)
                    <button @click="$dispatch('open-delete-modal')" type="button" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        {{ __('job.delete_btn') }}
                    </button>
                @endcan
                @if ($job->ended_at)
                    <a href="{{ route('admin.jobs.pdf', $job) }}" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ __('job.btn_download_pdf') }}
                    </a>
                @else
                    <span class="inline-flex items-center gap-2 rounded-md bg-gray-300 px-3 py-2 text-sm font-semibold text-gray-500 cursor-not-allowed" title="{{ __('job.pdf_active_blocked') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ __('job.btn_download_pdf') }}
                    </span>
                @endif
            </div>
        </x-slot>
    </x-page-header>

    {{-- Delete Confirmation Modal --}}
    @can('delete', $job)
        <div x-data="{ showConfirm: false, confirmation: '' }" @open-delete-modal.window="showConfirm = true; confirmation = ''" x-show="showConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50" @keydown.escape.window="showConfirm = false">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-4" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900">{{ __('job.delete_title') }}</h3>
                <p class="mt-2 text-sm text-red-600">{{ __('job.delete_warning') }}</p>

                <form method="POST" action="{{ route('admin.jobs.destroy', $job) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <label for="confirmation" class="block text-sm font-medium text-gray-700">{{ __('job.delete_confirm_label') }}</label>
                    <input type="text" name="confirmation" id="confirmation" x-model="confirmation" placeholder="{{ __('job.delete_confirm_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm">
                    @error('confirmation')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="mt-4 flex justify-end gap-3">
                        <button type="button" @click="showConfirm = false" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">{{ __('job.edit_cancel') }}</button>
                        <button type="submit" :disabled="confirmation !== '{{ __('job.delete_confirmation_word') }}'" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed">{{ __('job.delete_btn') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- Info Section --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_customer') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '–' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_object') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $job->customerObject?->name ?? '–' }}
                    @if($job->customerObject?->street)
                        <span class="text-gray-500">— {{ $job->customerObject->street }}, {{ $job->customerObject->zip }} {{ $job->customerObject->city }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_driver') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->user->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_type') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $job->type->label() }}
                    @if ($job->is_manual)
                        <span class="ml-1 inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20">{{ __('job.detail_manual_badge') }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_vehicle') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->vehicle?->displayLabel() ?? __('job.detail_vehicle_none') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_started_at') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_ended_at') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($job->ended_at)
                        {{ $job->localEndedAt()->format('d.m.Y H:i') }}
                        <span class="text-gray-500">({{ $job->durationFormatted() }})</span>
                        @if ($job->isInGracePeriod())
                            <span class="ml-2 inline-flex items-center gap-1 text-amber-600" title="{{ __('job.lock_open') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                <span class="text-xs">{{ __('job.lock_grace_remaining', ['hours' => round(now()->diffInHours($job->graceDeadline()))]) }}</span>
                            </span>
                        @elseif ($job->isLocked())
                            <span class="ml-2 inline-flex items-center gap-1 text-gray-500" title="{{ __('job.lock_closed') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span class="text-xs">{{ __('job.lock_since', ['date' => $job->graceDeadline()->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i')]) }}</span>
                            </span>
                        @endif
                    @else
                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('job.status_active') }}</span>
                    @endif
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_notes') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->notes ?? __('job.detail_notes_empty') }}</dd>
            </div>
        </dl>
    </div>

    {{-- Weather Section --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('job.detail_weather') }}</h2>

        @if ($job->weatherSnapshots->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_moment') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_provider') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_temperature') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_precipitation') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_snow_depth') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_wind_speed') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_humidity') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_weather_code') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('weather.col_fetched') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($job->weatherSnapshots as $snapshot)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $snapshot->moment->label() }}</td>
                                @if ($snapshot->fetched_at)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">{{ $snapshot->providerLabel() }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->temperature }} &deg;C</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->precipitation }} mm</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->snow_depth }} cm</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->wind_speed !== null ? $snapshot->wind_speed . ' km/h' : '–' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->humidity !== null ? $snapshot->humidity . ' %' : '–' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->weather_code }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $snapshot->fetched_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                                @else
                                    <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ __('job.weather_not_fetched') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <form method="POST" action="{{ route('admin.jobs.weather-retry', [$job, $snapshot->moment->value]) }}">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">{{ __('job.weather_retry_btn') }}</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Photos Section --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('job.detail_photos') }}</h2>

        @if ($job->jobPhotos->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach ($job->jobPhotos as $photo)
                    <div class="space-y-1">
                        <a href="{{ app(StorageBackendRegistry::class)->urlWithFallback($photo->file_path) }}" target="_blank" class="group block aspect-square rounded-lg overflow-hidden bg-gray-100 ring-1 ring-gray-200 hover:ring-indigo-400 transition">
                            <img src="{{ app(StorageBackendRegistry::class)->urlWithFallback($photo->thumbnail_path) }}" alt="{{ $photo->caption ?? __('job.detail_photo_alt') }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-200">
                        </a>
                        @if ($photo->annotated_path)
                            <a href="{{ app(StorageBackendRegistry::class)->urlWithFallback($photo->annotated_path) }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-900">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ __('job.detail_photo_annotated') }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __('job.detail_photos_empty') }}</p>
        @endif
    </div>

    {{-- GPS Map Section --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('job.detail_gps_track') }}</h2>

        @if ($job->gpsPoints->isNotEmpty())
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
        @else
            <p class="text-sm text-gray-500">{{ $job->is_manual ? __('job.detail_gps_empty_manual') : __('job.detail_gps_empty_no_signal') }}</p>
        @endif
    </div>

    {{-- Audit Trail Section --}}
    @can('viewAudit', $job)
        <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('job.audit_title') }}</h2>

            @if ($job->audits->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.audit_at') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.audit_by') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.audit_action_label') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.audit_old_value') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.audit_new_value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($job->audits->sortByDesc('created_at') as $audit)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $audit->created_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i:s') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $audit->user?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ __('job.audit_action_' . $audit->action) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if ($audit->old_values)
                                            @foreach ($audit->old_values as $field => $value)
                                                <div><span class="font-medium">{{ __('job.audit_field_' . $field) }}:</span> {{ $value ?? '—' }}</div>
                                            @endforeach
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if ($audit->new_values)
                                            @foreach ($audit->new_values as $field => $value)
                                                <div><span class="font-medium">{{ __('job.audit_field_' . $field) }}:</span> {{ $value ?? '—' }}</div>
                                            @endforeach
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500">{{ __('job.audit_no_entries') }}</p>
            @endif
        </div>
    @endcan

    @filterSlot('schneespur.admin.job.detail.after', [], $job)
</x-admin-layout>
