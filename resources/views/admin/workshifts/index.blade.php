<x-admin-layout>
    <x-slot name="header">{{ __('workshift.page_list') }} <x-help-icon topic="jobs" /></x-slot>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.workshifts.index') }}" class="mt-4 space-y-2">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('workshift.filter_driver') }}</label>
                <select name="user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('workshift.filter_all') }}</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected(request('user_id') == $driver->id)>{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="started_after" class="block text-sm font-medium text-gray-700">{{ __('workshift.filter_date_from') }}</label>
                <input type="date" name="started_after" id="started_after" value="{{ request('started_after') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="started_before" class="block text-sm font-medium text-gray-700">{{ __('workshift.filter_date_to') }}</label>
                <input type="date" name="started_before" id="started_before" value="{{ request('started_before') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('workshift.filter_btn') }}
                </button>
                <a href="{{ route('admin.workshifts.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('workshift.filter_reset') }}</a>
            </div>
        </div>
        <x-date-range-presets fromId="started_after" toId="started_before" />
    </form>

    <div class="mt-6">
        @if ($shifts->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('workshift.col_date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('workshift.col_driver') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('workshift.col_started_at') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('workshift.col_ended_at') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('workshift.col_jobs') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('workshift.col_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($shifts as $shift)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $shift->started_at->setTimezone(config('app.display_timezone'))->format('d.m.Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $shift->user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $shift->started_at->setTimezone(config('app.display_timezone'))->format('H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if ($shift->ended_at)
                                            {{ $shift->ended_at->setTimezone(config('app.display_timezone'))->format('H:i') }}
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('workshift.status_active') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('admin.workshifts.show', $shift) }}" class="text-indigo-600 hover:text-indigo-900">{{ $shift->jobs_count }} {{ trans_choice('workshift.jobs_label', $shift->jobs_count) }}</a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if ($shift->ended_at)
                                            <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">{{ __('workshift.status_completed') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('workshift.status_active') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $shifts->links() }}
            </div>
        @else
            <x-empty-state :heading="__('workshift.empty_heading')" :body="__('workshift.empty_body')" />
        @endif
    </div>
</x-admin-layout>
