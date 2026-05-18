<x-admin-layout>
    <x-slot name="header">
        <a href="{{ route('admin.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-900">&larr; {{ __('job.page_detail') }}</a>
        <span class="mx-2 text-gray-400">/</span>
        {{ __('job.edit_title') }} <x-help-icon topic="jobs" />
    </x-slot>

    <x-page-header :title="__('job.edit_title')" />

    <div class="mt-6 bg-white shadow-sm rounded-lg p-6">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 mb-6">
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_customer') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '–' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_object') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->customerObject?->name ?? '–' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_driver') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->user->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_type') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->type->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_started_at') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.detail_ended_at') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $job->localEndedAt()?->format('d.m.Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('job.edit_grace_remaining') }}</dt>
                <dd class="mt-1 text-sm text-amber-600 font-medium">
                    {{ $job->graceDeadline()->diffForHumans() }}
                </dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('admin.jobs.update', $job) }}">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="notes" :value="__('job.edit_notes_label')" />
                <textarea
                    id="notes"
                    name="notes"
                    rows="5"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    maxlength="2000"
                >{{ old('notes', $job->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('job.edit_save') }}</x-primary-button>
                <a href="{{ route('admin.jobs.show', $job) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('job.edit_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
