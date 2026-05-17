@php
    $season = $widget['data']['season'];
    $seasonJobCount = $widget['data']['seasonJobCount'];
    $seasonTotalMinutes = $widget['data']['seasonTotalMinutes'];
    $jobsPerMonth = $widget['data']['jobsPerMonth'];
@endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        @if ($season->isCurrent)
            {{ __('dashboard.season_current') }}
        @else
            {{ __('dashboard.season_last', ['label' => $season->label]) }}
        @endif
    </h3>

    @if ($seasonJobCount === 0)
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <p class="text-sm text-gray-500">{{ __('dashboard.no_jobs_in_season') }}</p>
        </div>
    @else
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <div class="text-sm font-medium text-gray-500">{{ __('dashboard.total_jobs') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $seasonJobCount }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">{{ __('dashboard.total_hours') }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ intdiv($seasonTotalMinutes, 60) }}h {{ $seasonTotalMinutes % 60 }}m</div>
                </div>
            </div>

            <h4 class="text-sm font-medium text-gray-700 mb-3">{{ __('dashboard.jobs_per_month') }}</h4>
            <div class="space-y-2">
                @foreach ($jobsPerMonth as $month => $count)
                    @php
                        $maxCount = $jobsPerMonth->max();
                        $widthPercent = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
                    @endphp
                    <div class="flex items-center">
                        <div class="w-12 text-xs font-medium text-gray-500">{{ __('dashboard.month_' . $month) }}</div>
                        <div class="flex-1 mx-3">
                            <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-4 bg-indigo-500 rounded-full" style="width: {{ $widthPercent }}%"></div>
                            </div>
                        </div>
                        <div class="w-8 text-xs font-semibold text-gray-700 text-right">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
