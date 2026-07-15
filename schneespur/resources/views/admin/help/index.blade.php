<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_help') }}</x-slot>

    <div class="mb-6">
        <p class="text-gray-600">{{ __('help.page_description', ['app_name' => brand()]) }}</p>
    </div>

    <div class="max-w-2xl space-y-4">
        @foreach($topics as $slug => $meta)
        <a href="{{ route('admin.help.show', $slug) }}"
           class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">{{ $meta['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $meta['description'] }}</p>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</x-admin-layout>
