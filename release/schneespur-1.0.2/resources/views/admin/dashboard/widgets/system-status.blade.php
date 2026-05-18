@php
    $pendingQueueJobs = $widget['data']['pendingQueueJobs'];
    $failedQueueJobs = $widget['data']['failedQueueJobs'];
    $photoCount = $widget['data']['photoCount'];
@endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.system_status') }}</h3>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('dashboard.pending_queue_jobs') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $pendingQueueJobs }}</div>
        </div>
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 {{ $failedQueueJobs > 0 ? 'border-l-4 border-red-500' : '' }}">
            <div class="text-sm font-medium {{ $failedQueueJobs > 0 ? 'text-red-600' : 'text-gray-500' }}">{{ __('dashboard.failed_queue_jobs') }}</div>
            <div class="mt-1 text-2xl font-semibold {{ $failedQueueJobs > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $failedQueueJobs }}</div>
        </div>
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('dashboard.photo_count') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $photoCount }}</div>
        </div>
    </div>
</div>
