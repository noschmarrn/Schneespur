<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_dashboard') }} <x-help-icon topic="jobs" /></x-slot>

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900">{{ __('admin.welcome_message', ['name' => Auth::user()->name]) }}</h2>
    </div>

    @foreach ($widgets as $widget)
        @if (!$widget['error'])
            @include($widget['view'], ['widget' => $widget])
        @endif
    @endforeach
</x-admin-layout>
