<x-portal-layout>
    <h1 class="text-2xl font-bold text-gray-900">{{ __('portal.notifications_title') }}</h1>

    <div class="mt-6">
        @if ($logs->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.notifications_col_date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.notifications_col_type') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.notifications_col_status') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('portal.notifications_col_recipient') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @switch($log->type)
                                            @case('job_completed')
                                                {{ __('portal.notifications_type_job_completed') }}
                                                @break
                                            @case('customer_report_email')
                                                {{ __('portal.notifications_type_customer_report') }}
                                                @break
                                            @case('portal_credentials')
                                                {{ __('portal.notifications_type_portal_credentials') }}
                                                @break
                                            @default
                                                {{ $log->type }}
                                        @endswitch
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if ($log->status === 'sent')
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('portal.notifications_status_sent') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">{{ __('portal.notifications_status_failed') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $log->recipient }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __('portal.notifications_empty') }}</p>
        @endif
    </div>
</x-portal-layout>
