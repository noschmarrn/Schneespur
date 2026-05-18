<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_overview_daily') }} <x-help-icon topic="overview" /></x-slot>

    {{-- Date navigation --}}
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('admin.overview.daily', ['date' => $prevDate->toDateString()]) }}"
           class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            {{ __('overview.prev') }}
        </a>

        <div class="flex items-center gap-3">
            <span class="text-lg font-semibold text-gray-900">
                {{ $date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') }}
            </span>
            @unless($date->isToday())
                <a href="{{ route('admin.overview.daily') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
                    {{ __('overview.today') }}
                </a>
            @endunless
        </div>

        <a href="{{ route('admin.overview.daily', ['date' => $nextDate->toDateString()]) }}"
           class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
            {{ __('overview.next') }}
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
        </a>
    </div>

    @if($totalJobs > 0)
        {{-- Summary card --}}
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 mb-6">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {{-- Total jobs --}}
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.total_jobs') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalJobs }}</dd>
                </div>

                {{-- Total duration --}}
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.total_duration') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">
                        @php $h = intdiv($totalMinutes, 60); $m = $totalMinutes % 60; @endphp
                        {{ $h > 0 ? $h . 'h ' . $m . 'min' : $m . 'min' }}
                    </dd>
                </div>

                {{-- Job type breakdown --}}
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.job_type_breakdown') }}</dt>
                    <dd class="mt-1 space-y-0.5">
                        @foreach($jobTypeBreakdown as $type => $count)
                            <div class="text-sm text-gray-900">{{ __('job.type_' . $type) }}: {{ $count }}</div>
                        @endforeach
                    </dd>
                </div>

                {{-- Weather summary --}}
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.weather_summary') }}</dt>
                    <dd class="mt-1">
                        @if($weatherSummary && $weatherSummary->temp_min !== null)
                            <div class="text-sm text-gray-900">
                                {{ __('overview.temperature_range', ['min' => number_format($weatherSummary->temp_min, 1), 'max' => number_format($weatherSummary->temp_max, 1)]) }}
                            </div>
                            <div class="text-sm text-gray-900">
                                {{ $weatherSummary->has_precipitation ? __('overview.precipitation_yes') : __('overview.precipitation_no') }}
                            </div>
                        @else
                            <div class="text-sm text-gray-500">{{ __('overview.weather_no_data') }}</div>
                        @endif
                    </dd>
                </div>
            </div>
        </div>

        {{-- Driver groups (shared partial) --}}
        @include('admin.overview.partials.day-detail')
    @else
        {{-- Empty state --}}
        <x-empty-state :heading="__('overview.no_jobs_today')" :body="__('overview.no_jobs_hint')">
            @if($lastJobDate)
                <x-slot name="action">
                    <a href="{{ route('admin.overview.daily', ['date' => $lastJobDate->toDateString()]) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('overview.last_job_day') }}: {{ $lastJobDate->format('d.m.Y') }}
                    </a>
                </x-slot>
            @endif
        </x-empty-state>
    @endif
</x-admin-layout>
