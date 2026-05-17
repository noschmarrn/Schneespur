<x-admin-layout>
    <x-slot name="header">{{ __('driver.gps_overview_title') }} <x-help-icon topic="owntracks" /></x-slot>

    @push('head')
    <meta http-equiv="refresh" content="60">
    @endpush

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('driver.gps_overview_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ __('driver.gps_auto_refresh_note') }}</p>
    </div>

    @if($drivers->isEmpty())
        <div class="bg-white shadow-sm rounded-lg p-8 text-center">
            <p class="text-gray-500">{{ __('driver.gps_no_drivers') }}</p>
        </div>
    @else
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.gps_col_driver') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.gps_col_status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.gps_col_last_seen') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.gps_col_battery') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.gps_col_active_job') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.gps_col_credentials') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($drivers as $driver)
                            @php
                                $hasCredentials = !empty($driver->owntracks_username);
                                $gps = $latestGps->get($driver->id);
                                $activeJob = $activeJobs->get($driver->id);

                                if (!$hasCredentials) {
                                    $statusClass = 'bg-gray-100 text-gray-500';
                                    $statusLabel = __('driver.gps_status_not_configured');
                                } elseif (!$gps) {
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    $statusLabel = __('driver.gps_status_no_data');
                                } else {
                                    $age = $now - $gps->timestamp;
                                    if ($age <= 300) {
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusLabel = __('driver.gps_status_online');
                                    } elseif ($age <= 3600) {
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        $statusLabel = __('driver.gps_status_idle');
                                    } else {
                                        $statusClass = 'bg-red-100 text-red-800';
                                        $statusLabel = __('driver.gps_status_offline');
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $driver->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($gps)
                                        {{ \Carbon\Carbon::createFromTimestamp($gps->timestamp)->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($gps && $gps->battery !== null)
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1 {{ $gps->battery <= 20 ? 'text-red-500' : ($gps->battery <= 50 ? 'text-yellow-500' : 'text-green-500') }}" fill="currentColor" viewBox="0 0 24 24"><path d="M17 4h-3V2h-4v2H7v18h10V4zm-1 16H8V6h8v14z"/><rect x="9" y="{{ 19 - round(13 * $gps->battery / 100) }}" width="6" height="{{ round(13 * $gps->battery / 100) }}" /></svg>
                                            {{ $gps->battery }}%
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($activeJob)
                                        <span class="text-gray-900 font-medium">{{ $activeJob->customer->name }}</span>
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ $activeJob->type->label() }}</span>
                                    @else
                                        {{ __('driver.gps_no_active_job') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($hasCredentials)
                                        <span class="text-green-600 font-medium">{{ __('driver.gps_credentials_configured') }}</span>
                                    @else
                                        <a href="{{ route('admin.drivers.edit', $driver) }}" class="text-red-600 hover:text-red-800 font-medium">{{ __('driver.gps_credentials_not_configured') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin-layout>
