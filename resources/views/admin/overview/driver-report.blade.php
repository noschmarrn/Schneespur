<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_overview_driver_report') }} <x-help-icon topic="overview" /></x-slot>

    {{-- Filter bar --}}
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('admin.overview.driver-report') }}" class="flex flex-wrap items-end gap-4">
            {{-- Driver select --}}
            <div class="flex-1 min-w-[180px]">
                <label for="driver" class="block text-sm font-medium text-gray-700 mb-1">{{ __('overview.driver_report_select_driver') }}</label>
                <select name="driver" id="driver" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">{{ __('overview.driver_report_select_driver') }}</option>
                    @php
                        $regularDrivers = $drivers->reject(fn ($d) => $d->isAnonymized());
                        $anonymizedDrivers = $drivers->filter(fn ($d) => $d->isAnonymized());
                    @endphp
                    @foreach($regularDrivers as $driver)
                        <option value="{{ $driver->id }}" @selected($selectedDriver && $selectedDriver->id === $driver->id)>{{ $driver->displayName() }}</option>
                    @endforeach
                    @if($anonymizedDrivers->isNotEmpty())
                        <optgroup label="—">
                            @foreach($anonymizedDrivers as $driver)
                                <option value="{{ $driver->id }}" @selected($selectedDriver && $selectedDriver->id === $driver->id)>{{ $driver->displayName() }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>

            {{-- Date from --}}
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">{{ __('overview.report_date_from') }}</label>
                <input type="date" name="from" id="from" value="{{ $from->format('Y-m-d') }}" class="block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            {{-- Date to --}}
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">{{ __('overview.report_date_to') }}</label>
                <input type="date" name="to" id="to" value="{{ $to->format('Y-m-d') }}" class="block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            {{-- Apply button --}}
            <div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    {{ __('overview.report_filter_apply') }}
                </button>
            </div>
        </form>

        {{-- Quick-filter buttons --}}
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
            @foreach(['week' => 'report_filter_week', 'month' => 'report_filter_month', '30days' => 'report_filter_30days', 'season' => 'report_filter_season'] as $key => $label)
                <a href="{{ route('admin.overview.driver-report', array_merge(['driver' => $selectedDriver?->id], $quickFilters[$key])) }}"
                   class="px-3 py-1.5 text-sm rounded-md border {{ $from->format('Y-m-d') === $quickFilters[$key]['from'] && $to->format('Y-m-d') === $quickFilters[$key]['to'] ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }} transition">
                    {{ __('overview.' . $label) }}
                </a>
            @endforeach
        </div>
    </div>

    @if(!$selectedDriver)
        {{-- No driver selected --}}
        <x-empty-state :heading="__('overview.driver_report_no_driver_selected')" />
    @elseif($totalJobs > 0)
        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {{-- Total jobs --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_total_jobs') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalJobs }}</dd>
            </div>

            {{-- Total hours --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_total_hours') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                    @php $h = intdiv($totalMinutes, 60); $m = $totalMinutes % 60; @endphp
                    {{ $h > 0 ? $h . 'h ' . $m . 'min' : $m . 'min' }}
                </dd>
            </div>

            {{-- Customer count --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_customer_count') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $customerCount }}</dd>
            </div>

            {{-- Job type breakdown --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_type_breakdown') }}</dt>
                <dd class="mt-1 space-y-0.5">
                    @foreach($jobTypeBreakdown as $type => $count)
                        <div class="text-sm text-gray-900">{{ __('job.type_' . $type) }}: {{ $count }}</div>
                    @endforeach
                </dd>
            </div>
        </div>

        {{-- Shift Summary Cards --}}
        @if($shiftCount > 0)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_shift_count') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $shiftCount }}</dd>
                </div>

                <div class="bg-white rounded-lg shadow p-4">
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_shift_total') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">
                        @php $sh = intdiv($totalShiftMinutes, 60); $sm = $totalShiftMinutes % 60; @endphp
                        {{ $sh > 0 ? $sh . 'h ' . $sm . 'min' : $sm . 'min' }}
                    </dd>
                </div>

                <div class="bg-white rounded-lg shadow p-4">
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.driver_report_shift_avg') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">
                        @php $ah = intdiv($avgShiftMinutes, 60); $am = $avgShiftMinutes % 60; @endphp
                        {{ $ah > 0 ? $ah . 'h ' . $am . 'min' : $am . 'min' }}
                    </dd>
                </div>
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <p class="text-sm text-gray-500">{{ __('overview.driver_report_no_shifts') }}</p>
            </div>
        @endif

        {{-- Job Table --}}
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_customer') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_start') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_end') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($jobs as $job)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('admin.jobs.show', $job) }}" class="text-gray-900 hover:text-indigo-600">{{ $job->localStartedAt()->format('d.m.Y') }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '—' }}
                                    @if($job->customerObject)
                                        <span class="text-gray-400">/ {{ $job->customerObject->name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->type->label() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->localStartedAt()->format('H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->ended_at ? $job->localEndedAt()->format('H:i') : '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    @if($job->ended_at)
                                        @php $jm = $job->started_at->diffInMinutes($job->ended_at); $jh = intdiv($jm, 60); $jr = $jm % 60; @endphp
                                        {{ $jh > 0 ? $jh . 'h ' . $jr . 'min' : $jr . 'min' }}
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">
                                            {{ __('overview.active_badge') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Driver selected but no jobs --}}
        <x-empty-state :heading="__('overview.driver_report_no_jobs')" />
    @endif
</x-admin-layout>
