<x-admin-layout>
    <x-slot name="header">{{ __('driver.page_create') }} <x-help-icon topic="drivers" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_drivers'), 'url' => route('admin.drivers.index')],
        ['label' => __('driver.page_create')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('driver.page_create')" />

        <form method="POST" action="{{ route('admin.drivers.store') }}" class="mt-6">
            @csrf

            @include('admin.drivers._form', ['driver' => new \App\Models\User])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_create') }}</x-primary-button>
                <a href="{{ route('admin.drivers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
