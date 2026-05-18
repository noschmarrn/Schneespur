<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_help') }}</x-slot>

    <div class="mb-6">
        <p class="text-gray-600">{{ __('help.page_description', ['app_name' => brand()]) }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($topics as $slug => $langKey)
        <a href="{{ route('admin.help.show', $slug) }}"
           class="block bg-white rounded-lg border border-gray-200 p-5 hover:border-blue-300 hover:shadow-md transition-all">
            <h3 class="text-base font-semibold text-gray-900 mb-1">{{ __($langKey) }}</h3>
            <p class="text-sm text-gray-500">{{ __($langKey . '_desc', ['app_name' => brand()]) }}</p>
        </a>
        @endforeach
    </div>
</x-admin-layout>
