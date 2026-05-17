@php $recentJobs = $widget['data']['recentJobs']; @endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.recent_jobs') }}</h3>

    @if ($recentJobs->isEmpty())
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <p class="text-sm text-gray-500">{{ __('dashboard.no_recent_jobs') }}</p>
        </div>
    @else
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dashboard.col_date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dashboard.col_customer') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dashboard.col_type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dashboard.col_driver_short') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dashboard.col_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($recentJobs as $job)
                            <tr class="{{ Route::has('admin.jobs.show') ? 'cursor-pointer hover:bg-gray-50' : '' }}"
                                @if (Route::has('admin.jobs.show')) onclick="window.location='{{ route('admin.jobs.show', $job) }}'" @endif>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '—' }}
                                    @if($job->customerObject)
                                        <span class="text-gray-400">/ {{ $job->customerObject->name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">{{ $job->type?->label() ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">{{ $job->user?->displayName() ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right">
                                    @if ($job->ended_at)
                                        @php $mins = $job->started_at->diffInMinutes($job->ended_at); @endphp
                                        {{ intdiv($mins, 60) }}h {{ $mins % 60 }}m
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
