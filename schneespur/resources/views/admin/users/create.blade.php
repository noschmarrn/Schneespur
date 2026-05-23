<x-admin-layout>
    <x-slot name="header">{{ __('user.heading_create') }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_users'), 'url' => route('admin.users.index')],
        ['label' => __('user.heading_create')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('user.heading_create')" />

        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6">
            @csrf

            @include('admin.users._form', ['user' => new \App\Models\User, 'roles' => $roles])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('user.button_create') }}</x-primary-button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
