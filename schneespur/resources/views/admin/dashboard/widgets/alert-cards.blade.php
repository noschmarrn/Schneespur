@php
    $alertCounts = $widget['data']['alertCounts'];
    $alertTypes = $widget['data']['alertTypes'];
@endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('alert.dashboard_heading') }}</h3>

    @if ($alertCounts['total'] === 0)
        <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-6">
            <div class="flex items-center">
                <svg class="h-6 w-6 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <div class="text-sm font-medium text-green-800">{{ __('alert.card_all_clear') }}</div>
                    <div class="text-sm text-green-700">{{ __('alert.card_all_clear_body') }}</div>
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            @foreach ($alertTypes as $type => $config)
                <a href="{{ route('admin.alerts.index', ['type' => $type]) }}" class="block border-l-4 {{ $config['border'] }} {{ $config['bg'] }} overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition-shadow duration-150">
                    <div class="text-sm font-medium {{ $config['text'] }}">{{ __('alert.type_' . $type) }}</div>
                    <div class="mt-1 text-3xl font-semibold {{ $config['count_text'] }}">{{ $alertCounts[$type] }}</div>

                    @if ($alertCounts[$type] > 0)
                        <div class="mt-3 text-xs {{ $config['text'] }}">
                            <div class="font-medium mb-1">{{ __('alert.card_recent_jobs') }}</div>
                            <ul class="space-y-0.5">
                                @foreach ($config['recentJobs'] as $alertJob)
                                    <li class="truncate">{{ $alertJob->customer->name }} — {{ $alertJob->started_at->format(__('alert.date_format')) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mt-3 text-xs font-medium {{ $config['text'] }} underline">{{ __('alert.card_view_all') }} &rarr;</div>
                </a>
            @endforeach
        </div>
    @endif
</div>
