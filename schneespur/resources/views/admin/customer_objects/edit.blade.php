<x-admin-layout>
    <x-slot name="header">{{ __('customer_object.page_edit') }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_customers'), 'url' => route('admin.customers.index')],
        ['label' => $customer->name, 'url' => route('admin.customers.objects.index', $customer)],
        ['label' => __('customer_object.page_edit')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('customer_object.page_edit')" />

        <form method="POST" action="{{ route('admin.customers.objects.update', [$customer, $object]) }}" class="mt-6">
            @csrf
            @method('PUT')

            @include('admin.customer_objects._form', ['object' => $object])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_save') }}</x-primary-button>
                <a href="{{ route('admin.customers.objects.index', $customer) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
