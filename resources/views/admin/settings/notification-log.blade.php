<x-admin-layout>
    <x-slot name="header">{{ __('notification.page_notification_log') }} <x-help-icon topic="settings" /></x-slot>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.settings.notification-log') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">{{ __('notification.filter_status') }}</label>
            <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">{{ __('notification.filter_all') }}</option>
                <option value="sent" @selected(request('status') === 'sent')>{{ __('notification.status_sent') }}</option>
                <option value="failed" @selected(request('status') === 'failed')>{{ __('notification.status_failed') }}</option>
            </select>
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">{{ __('notification.filter_type') }}</label>
            <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">{{ __('notification.filter_all') }}</option>
                <option value="job_completed" @selected(request('type') === 'job_completed')>{{ __('notification.type_job_completed') }}</option>
                <option value="customer_report" @selected(request('type') === 'customer_report')>{{ __('notification.type_customer_report') }}</option>
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-sm font-medium text-gray-700">{{ __('notification.filter_date_from') }}</label>
            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div>
            <label for="date_to" class="block text-sm font-medium text-gray-700">{{ __('notification.filter_date_to') }}</label>
            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('notification.filter_btn') }}
            </button>
            <a href="{{ route('admin.settings.notification-log') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('notification.filter_reset') }}</a>
        </div>
    </form>

    <div class="mt-6">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('notification.col_date') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('notification.col_customer') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('notification.col_recipient') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('notification.col_type') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('notification.col_status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('notification.col_error') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($logs as $log)
                            <tr class="{{ $log->status === 'failed' ? 'bg-red-50' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->created_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->customer_name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->recipient ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ __('notification.type_' . $log->type, [], null) !== 'notification.type_' . $log->type ? __('notification.type_' . $log->type) : $log->type }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($log->status === 'sent')
                                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('notification.status_sent') }}</span>
                                    @elseif ($log->status === 'failed')
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">{{ __('notification.status_failed') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20">{{ __('notification.status_skipped') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $log->error_message }}">{{ $log->error_message ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('notification.empty_log') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</x-admin-layout>
