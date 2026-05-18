<x-admin-layout>
    <x-slot name="header">{{ __('customer.page_list') }} <x-help-icon topic="customers" /></x-slot>

    <div class="flex items-center justify-end">
        <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('customer.btn_create') }}
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.customers.index') }}" class="mt-4">
        <x-text-input name="search" :value="request('search')" :placeholder="__('ui.search_placeholder')" class="w-full sm:w-64" />
    </form>

    <div class="mt-6">
        @if ($customers->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer.col_name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer.col_address') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer.col_contact') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer.col_notify') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($customers as $customer)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $customer->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @php $firstObject = $customer->objects->first(); @endphp
                                        @if ($firstObject)
                                            @if ($firstObject->street)
                                                <span class="block">{{ $firstObject->street }}</span>
                                            @endif
                                            <span>{{ implode(' ', array_filter([$firstObject->zip, $firstObject->city])) }}</span>
                                            @if ($customer->objects->count() > 1)
                                                <span class="text-xs text-gray-400">(+{{ $customer->objects->count() - 1 }})</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $customer->contact_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if ($customer->auto_notify_email)
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                {{ __('customer.badge_notify_yes') }}
                                            </span>
                                        @endif
                                        @if ($customer->portal_enabled)
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/20">
                                                {{ __('customer.badge_portal') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.exports.customer-pdf', ['customer' => $customer->id]) }}" class="text-gray-600 hover:text-gray-900 mr-3" title="{{ __('customer.btn_download_report') }}">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                        </a>
                                        <a href="{{ route('admin.customers.objects.index', $customer) }}" class="text-gray-600 hover:text-gray-900 mr-3" title="{{ __('customer_object.btn_objects') }}">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18m16.5-18v18M5.25 3h13.5M5.25 21h13.5M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15" /></svg>
                                        </a>
                                        <a href="{{ route('admin.customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('ui.button_edit') }}</a>

                                        <button x-data x-on:click="$dispatch('open-modal', 'delete-customer-{{ $customer->id }}')" type="button" class="ml-3 text-red-600 hover:text-red-900">
                                            {{ __('ui.button_delete') }}
                                        </button>

                                        <x-confirm-dialog
                                            :name="'delete-customer-' . $customer->id"
                                            :title="__('customer.modal_delete_title')"
                                            :message="__('customer.modal_delete_body1', ['name' => e($customer->name)])"
                                        >
                                            <x-slot name="action">
                                                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-danger-button>{{ __('customer.modal_delete_submit') }}</x-danger-button>
                                                </form>
                                            </x-slot>
                                        </x-confirm-dialog>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        @else
            <x-empty-state :heading="__('customer.empty_heading')" :body="__('customer.empty_body')">
                <x-slot name="action">
                    <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('customer.empty_cta') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @endif
    </div>
</x-admin-layout>
