<x-driver-layout>
    <div class="space-y-4">
        <h1 class="text-xl font-bold text-gray-100">{{ __('driver.history_title') }}</h1>

        @forelse($jobs as $job)
            <a href="{{ route('driver.jobs.show', $job) }}" class="block bg-gray-800 rounded-xl p-4 space-y-2 hover:bg-gray-750 transition">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">{{ $job->localStartedAt()->format('d.m.Y') }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-900 text-blue-300">
                        {{ $job->type->label() }}
                    </span>
                </div>

                <p class="text-base font-semibold text-gray-100">
                    {{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '–' }}
                    @if($job->customerObject)
                        <span class="text-gray-400 text-sm font-normal">/ {{ $job->customerObject->name }}</span>
                    @endif
                </p>

                <div class="flex items-center gap-4 text-sm text-gray-400">
                    <span>
                        @if($job->ended_at)
                            {{ $job->durationFormatted() }}
                        @else
                            <span class="text-green-400">{{ __('driver.history_duration_active') }}</span>
                        @endif
                    </span>

                    @if($job->job_photos_count > 0)
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $job->job_photos_count }}
                        </span>
                    @endif

                    <span class="text-gray-500">{{ $job->localStartedAt()->format('H:i') }}@if($job->ended_at)–{{ $job->localEndedAt()->format('H:i') }}@endif</span>
                </div>
            </a>
        @empty
            <div class="flex flex-col items-center justify-center py-20">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-800 mb-4">
                    <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-400">{{ __('driver.history_empty_heading') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('driver.history_empty_body') }}</p>
            </div>
        @endforelse

        <div class="pt-2">
            {{ $jobs->links() }}
        </div>
    </div>
</x-driver-layout>
