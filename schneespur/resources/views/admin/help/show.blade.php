<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_help') }} — {{ $title }}</x-slot>

    <div class="flex flex-col lg:flex-row gap-6">
        {{-- Sidebar --}}
        <div class="lg:w-56 shrink-0">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <a href="{{ route('admin.help.index') }}" class="flex items-center text-sm text-gray-500 hover:text-gray-700 mb-3">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    {{ __('help.back_to_overview') }}
                </a>
                @include('admin.help._sidebar')
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0 space-y-6">
            @includeIf($view)
        </div>
    </div>
</x-admin-layout>
