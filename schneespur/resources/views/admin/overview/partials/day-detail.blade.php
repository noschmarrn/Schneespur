{{-- Shared day-detail partial: used by AJAX drill-down (isInline=true) and standalone daily view --}}
@php $isInline = $isInline ?? false; @endphp

@if($totalJobs > 0)
    @if($isInline)
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">
                {{ $date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') }}
                <span class="text-sm font-normal text-gray-500">&mdash; {{ trans_choice('overview.jobs_count', $totalJobs, ['count' => $totalJobs]) }}</span>
            </h3>
            <a href="{{ route('admin.overview.daily', ['date' => $date->toDateString()]) }}"
               class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
                {{ __('overview.view_full_day') }}
            </a>
        </div>
    @endif

    @foreach($driverSummaries as $summary)
        <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-4">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $summary->user->name ?? __('overview.driver_group') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ trans_choice('overview.jobs_count', $summary->job_count, ['count' => $summary->job_count]) }}
                        &middot;
                        @php $dh = intdiv($summary->total_minutes, 60); $dm = $summary->total_minutes % 60; @endphp
                        {{ $dh > 0 ? $dh . 'h ' . $dm . 'min' : $dm . 'min' }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.field_started_at') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_customer') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_type') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_duration') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($summary->jobs as $job)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->localStartedAt()->format('H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '—' }}
                                    @if($job->customerObject)
                                        <span class="text-gray-400">/ {{ $job->customerObject->name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->type->label() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    @if($job->ended_at)
                                        @php $jm = $job->started_at->diffInMinutes($job->ended_at); $jh = intdiv($jm, 60); $jr = $jm % 60; @endphp
                                        {{ $jh > 0 ? $jh . 'h ' . $jr . 'min' : $jr . 'min' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    @if(!$job->ended_at)
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
    @endforeach
@else
    <p class="text-sm text-gray-500 py-4">{{ __('overview.no_jobs_today') }}</p>
@endif
