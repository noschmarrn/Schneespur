<x-admin-layout>
    <x-slot name="header">{{ __('customer.page_create') }} <x-help-icon topic="customers" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_customers'), 'url' => route('admin.customers.index')],
        ['label' => __('customer.page_create')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('customer.page_create')" />

        <form method="POST" action="{{ route('admin.customers.store') }}" class="mt-6">
            @csrf

            @include('admin.customers._form', ['customer' => new \App\Models\Customer])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_create') }}</x-primary-button>
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
