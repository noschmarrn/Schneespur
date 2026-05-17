<x-admin-layout>
    <x-slot name="header">{{ __('vehicle.page_create') }} <x-help-icon topic="first-steps" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_vehicles'), 'url' => route('admin.vehicles.index')],
        ['label' => __('vehicle.page_create')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('vehicle.page_create')" />

        <form method="POST" action="{{ route('admin.vehicles.store') }}" class="mt-6">
            @csrf

            @include('admin.vehicles._form', ['vehicle' => new \App\Models\Vehicle])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_create') }}</x-primary-button>
                <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
