<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_settings') }} <x-help-icon topic="settings" /></x-slot>

    @php
        $cards = [
            [
                'route' => 'admin.settings.branding',
                'title' => __('ui.branding_title'),
                'desc'  => __('ui.branding_description'),
                'icon'  => 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42',
            ],
            [
                'route' => 'admin.settings.email',
                'title' => __('notification.settings_card_email'),
                'desc'  => __('notification.settings_card_email_desc'),
                'icon'  => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
            ],
            [
                'route' => 'admin.settings.notification-log',
                'title' => __('notification.settings_card_log'),
                'desc'  => __('notification.settings_card_log_desc'),
                'icon'  => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
            ],
            [
                'route' => 'admin.settings.company',
                'title' => __('settings.company_title'),
                'desc'  => __('settings.company_description'),
                'icon'  => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z',
            ],
            [
                'route' => 'admin.settings.retention',
                'title' => __('settings.retention_title'),
                'desc'  => __('settings.retention_description'),
                'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
            [
                'route' => 'admin.settings.dispatch',
                'title' => __('dispatch.settings_title'),
                'desc'  => __('dispatch.settings_description'),
                'icon'  => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
            ],
            [
                'route' => 'admin.settings.weather',
                'title' => __('weather.settings_title'),
                'desc'  => __('weather.settings_description'),
                'icon'  => 'M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z',
            ],
            [
                'route' => 'admin.settings.backup',
                'title' => __('backup.settings_title'),
                'desc'  => __('backup.settings_description'),
                'icon'  => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
            ],
            [
                'route' => 'admin.settings.update',
                'title' => __('update.settings_title'),
                'desc'  => __('update.settings_description'),
                'icon'  => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
            ],
            [
                'route' => 'admin.settings.modules.index',
                'title' => __('modules.settings_card_title'),
                'desc'  => __('modules.settings_card_description'),
                'icon'  => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
            ],
        ];
    @endphp

    <div class="max-w-2xl space-y-4">
        @foreach ($cards as $card)
            <a href="{{ route($card['route']) }}" class="block bg-white shadow-sm rounded-lg p-6 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">{{ $card['title'] }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $card['desc'] }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</x-admin-layout>
