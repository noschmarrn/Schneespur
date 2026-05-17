<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_settings') }} <x-help-icon topic="settings" /></x-slot>

    <div class="max-w-2xl space-y-4">
        <a href="{{ route('admin.settings.branding') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('ui.branding_title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('ui.branding_description') }}</p>
        </a>
        <a href="{{ route('admin.settings.email') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('notification.settings_card_email') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('notification.settings_card_email_desc') }}</p>
        </a>
        <a href="{{ route('admin.settings.notification-log') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('notification.settings_card_log') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('notification.settings_card_log_desc') }}</p>
        </a>
        <a href="{{ route('admin.settings.company') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('settings.company_title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('settings.company_description') }}</p>
        </a>
        <a href="{{ route('admin.settings.retention') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('settings.retention_title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('settings.retention_description') }}</p>
        </a>
        <a href="{{ route('admin.settings.weather') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('weather.settings_title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('weather.settings_description') }}</p>
        </a>
        <a href="{{ route('admin.settings.update') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <h3 class="text-sm font-medium text-gray-900">{{ __('update.settings_title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('update.settings_description') }}</p>
        </a>
        <a href="{{ route('admin.settings.modules.index') }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">{{ __('modules.settings_card_title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('modules.settings_card_description') }}</p>
                </div>
            </div>
        </a>
    </div>
</x-admin-layout>
