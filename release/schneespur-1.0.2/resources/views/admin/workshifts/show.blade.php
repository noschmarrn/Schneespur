<x-admin-layout>
    <x-slot name="header">{{ __('workshift.page_detail') }}</x-slot>

    <x-page-header :title="__('workshift.page_detail')">
        <x-slot name="action">
            <a href="{{ route('admin.workshifts.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">&larr; {{ __('workshift.back_to_list') }}</a>
        </x-slot>
    </x-page-header>

    {{-- Shift info --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">{{ __('workshift.detail_info') }}</h3>
        </div>
        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('workshift.col_driver') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $workShift->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('workshift.col_started_at') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $workShift->started_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('workshift.col_ended_at') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if ($workShift->ended_at)
                            {{ $workShift->ended_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}
                        @else
                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('workshift.status_active') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('workshift.col_duration') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $duration ?? '–' }}</dd>
                </div>
            </dl>
            @if ($workShift->notes)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500">{{ __('workshift.col_notes') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $workShift->notes }}</dd>
                </div>
            @endif
        </div>
    </div>

    {{-- Jobs in this shift --}}
    <div class="mt-6 bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">{{ __('workshift.detail_jobs_heading', ['count' => $workShift->jobs->count()]) }}</h3>
        </div>
        @if ($workShift->jobs->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_customer') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_type') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_started_at') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_ended_at') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_status') }}</th>
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">{{ __('workshift.col_actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($workShift->jobs as $job)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->customer->name ?? '–' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->type->label() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->localStartedAt()->format('H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if ($job->ended_at)
                                        {{ $job->localEndedAt()->format('H:i') }}
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('job.status_active') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($job->ended_at)
                                        <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">{{ __('job.status_completed') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('job.status_active') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('admin.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('workshift.view_job') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-8 text-center text-sm text-gray-500">
                {{ __('workshift.no_jobs_in_shift') }}
            </div>
        @endif
    </div>
</x-admin-layout>
