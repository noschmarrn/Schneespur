<x-admin-layout>
    <x-slot name="header">{{ __('customer_object.page_list', ['customer' => $customer->name]) }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_customers'), 'url' => route('admin.customers.index')],
        ['label' => $customer->name, 'url' => route('admin.customers.edit', $customer)],
        ['label' => __('customer_object.btn_objects')],
    ]" />

    <div class="mt-4 flex items-center justify-between">
        <a href="{{ route('admin.customers.edit', $customer) }}" class="text-sm text-gray-600 hover:text-gray-900">
            &larr; {{ __('customer_object.btn_back_to_customer') }}
        </a>
        <a href="{{ route('admin.customers.objects.create', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('customer_object.btn_create') }}
        </a>
    </div>

    <div class="mt-6">
        @if ($objects->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer_object.col_name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer_object.col_address') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer_object.col_contact') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer_object.col_notify') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('customer_object.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($objects as $object)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $object->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if ($object->street)
                                            <span class="block">{{ $object->street }}</span>
                                        @endif
                                        <span>{{ implode(' ', array_filter([$object->zip, $object->city])) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $object->contact_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if ($object->auto_notify_email)
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                {{ __('customer_object.badge_notify_yes') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.customers.objects.edit', [$customer, $object]) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('ui.button_edit') }}</a>

                                        <button x-data x-on:click="$dispatch('open-modal', 'delete-object-{{ $object->id }}')" type="button" class="ml-3 text-red-600 hover:text-red-900">
                                            {{ __('ui.button_delete') }}
                                        </button>

                                        <x-confirm-dialog
                                            :name="'delete-object-' . $object->id"
                                            :title="__('customer_object.modal_delete_title')"
                                            :message="__('customer_object.modal_delete_body1', ['name' => $object->name])"
                                        >
                                            <x-slot name="action">
                                                <form method="POST" action="{{ route('admin.customers.objects.destroy', [$customer, $object]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-danger-button>{{ __('customer_object.modal_delete_submit') }}</x-danger-button>
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
        @else
            <x-empty-state :heading="__('customer_object.empty_heading')" :body="__('customer_object.empty_body')">
                <x-slot name="action">
                    <a href="{{ route('admin.customers.objects.create', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('customer_object.empty_cta') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @endif
    </div>
</x-admin-layout>
