@php
    $updateState = $widget['data']['updateState'];
    $lastCheck = $updateState['last_check'] ?? null;
    $hasUpdate = $lastCheck['has_update'] ?? false;
    $borderColor = $hasUpdate ? 'border-yellow-500' : ($lastCheck ? 'border-green-500' : 'border-gray-300');
    $bgColor = $hasUpdate ? 'bg-yellow-50' : ($lastCheck ? 'bg-green-50' : 'bg-gray-50');
@endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('update.dashboard_title') }}</h3>
    <a href="{{ route('admin.settings.update') }}" class="block border-l-4 {{ $borderColor }} {{ $bgColor }} overflow-hidden shadow-sm rounded-lg p-6 hover:shadow-md transition-shadow duration-150">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-500">{{ __('update.dashboard_version') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ config('app.version', '1.0.0') }}</div>
            </div>
            <div class="text-right">
                @if ($hasUpdate)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        {{ __('update.dashboard_update_available') }}
                    </span>
                    <div class="mt-1 text-sm text-yellow-700">{{ $lastCheck['latest_version'] ?? '' }}</div>
                @elseif ($lastCheck)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ __('update.dashboard_up_to_date') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        {{ __('update.dashboard_never_checked') }}
                    </span>
                @endif
            </div>
        </div>
        @if ($lastCheck && ($lastCheck['checked_at'] ?? null))
            <div class="mt-2 text-xs text-gray-500">
                {{ __('update.dashboard_last_checked') }}: {{ \Carbon\Carbon::parse($lastCheck['checked_at'])->diffForHumans() }}
            </div>
        @endif
    </a>
</div>
