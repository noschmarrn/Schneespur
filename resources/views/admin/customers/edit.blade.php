<x-admin-layout>
    <x-slot name="header">{{ __('customer.page_edit') }} <x-help-icon topic="customers" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_customers'), 'url' => route('admin.customers.index')],
        ['label' => __('customer.page_edit')],
    ]" />

    <div class="mt-4 flex justify-end">
        <a href="{{ route('admin.customers.objects.index', $customer) }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18m16.5-18v18M5.25 3h13.5M5.25 21h13.5M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15" /></svg>
            {{ __('customer_object.btn_objects') }} ({{ $customer->objects()->count() }})
        </a>
    </div>

    <div class="mt-4 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('customer.page_edit')" />

        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-6">
            @csrf
            @method('PUT')

            @include('admin.customers._form', ['customer' => $customer])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_save') }}</x-primary-button>
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>

    @include('admin.customers._portal-section', ['customer' => $customer])
</x-admin-layout>
