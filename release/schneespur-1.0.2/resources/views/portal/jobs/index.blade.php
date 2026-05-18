<x-portal-layout>
    <h1 class="text-2xl font-bold text-gray-900">{{ __('portal.jobs_title') }}</h1>

    {{-- Filters --}}
    <form method="GET" action="{{ route('portal.jobs.index') }}" class="mt-4 space-y-2">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="customer_object_id" class="block text-sm font-medium text-gray-700">{{ __('portal.jobs_filter_object') }}</label>
                <select name="customer_object_id" id="customer_object_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('portal.jobs_filter_all') }}</option>
                    @foreach ($objects as $object)
                        <option value="{{ $object->id }}" @selected(request('customer_object_id') == $object->id)>{{ $object->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="started_after" class="block text-sm font-medium text-gray-700">{{ __('portal.jobs_filter_date_from') }}</label>
                <input type="date" name="started_after" id="started_after" value="{{ request('started_after') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="started_before" class="block text-sm font-medium text-gray-700">{{ __('portal.jobs_filter_date_to') }}</label>
                <input type="date" name="started_before" id="started_before" value="{{ request('started_before') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">{{ __('portal.jobs_filter_type') }}</label>
                <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('portal.jobs_filter_all') }}</option>
                    @foreach ($jobTypes as $jobType)
                        <option value="{{ $jobType->value }}" @selected(request('type') === $jobType->value)>{{ $jobType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('portal.jobs_filter_btn') }}
                </button>
                <a href="{{ route('portal.jobs.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('portal.jobs_filter_reset') }}</a>
            </div>
        </div>
        <x-date-range-presets fromId="started_after" toId="started_before" />
    </form>

    {{-- Results --}}
    <div class="mt-6">
        @if ($jobs->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.jobs_col_date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.jobs_col_object') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.jobs_col_type') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.jobs_col_duration') }}</th>
                                @if ($customer->portal_show_driver_name)
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.jobs_col_driver') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($jobs as $job)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('portal.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-900">
                                            {{ $job->localStartedAt()->format('d.m.Y H:i') }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->customerObject?->name ?? '–' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->type->label() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->durationFormatted() }}</td>
                                    @if ($customer->portal_show_driver_name)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if ($job->user)
                                                {{ collect(explode(' ', trim($job->user->name)))->last() }}
                                            @else
                                                –
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $jobs->links() }}
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __('portal.jobs_empty') }}</p>
        @endif
    </div>
</x-portal-layout>
