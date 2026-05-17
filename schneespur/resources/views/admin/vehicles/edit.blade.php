<x-admin-layout>
    <x-slot name="header">{{ __('vehicle.page_edit') }} <x-help-icon topic="first-steps" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_vehicles'), 'url' => route('admin.vehicles.index')],
        ['label' => __('vehicle.page_edit')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('vehicle.page_edit')" />

        <form method="POST" action="{{ route('admin.vehicles.update', $vehicle) }}" class="mt-6">
            @csrf
            @method('PUT')

            @include('admin.vehicles._form', ['vehicle' => $vehicle])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_save') }}</x-primary-button>
                <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
