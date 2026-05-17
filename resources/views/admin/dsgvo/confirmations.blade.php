<x-admin-layout>
    <x-slot name="header">{{ __('dsgvo.admin_confirmations_heading') }} <x-help-icon topic="dsgvo" /></x-slot>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.dsgvo.confirmations') }}" class="mt-4">
        <x-text-input name="search" :value="request('search')" :placeholder="__('ui.search_placeholder')" class="w-full sm:w-64" />
    </form>

    <div class="mt-6">
        @if ($confirmations->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dsgvo.admin_confirmations_col_driver') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dsgvo.admin_confirmations_col_date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dsgvo.admin_confirmations_col_version') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dsgvo.admin_confirmations_col_ip') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dsgvo.admin_confirmations_col_action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($confirmations as $confirmation)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $confirmation->driver?->displayName() ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $confirmation->confirmed_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $confirmation->template_version }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $confirmation->ip_address ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.dsgvo.confirmations.show', $confirmation) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('dsgvo.admin_snapshot_title') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $confirmations->links() }}
            </div>
        @else
            <x-empty-state :heading="__('dsgvo.admin_confirmations_empty_heading')" :body="__('dsgvo.admin_confirmations_empty_body')" />
        @endif
    </div>
</x-admin-layout>
