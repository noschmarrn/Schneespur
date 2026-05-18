<x-admin-layout>
    <x-slot name="header">{{ __('driver.credentials_page_title') }} <x-help-icon topic="owntracks" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_drivers'), 'url' => route('admin.drivers.index')],
        ['label' => __('driver.credentials_page_title')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ __('driver.credentials_heading', ['name' => $driver->name]) }}</h2>
        </div>

        <div class="mt-6 space-y-4 max-w-lg mx-auto">
            {{-- Username --}}
            <div class="rounded-lg border border-gray-200 p-4" x-data="{ copied: false }">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium text-gray-500">{{ __('driver.field_owntracks_username') }}</span>
                        <p class="mt-1 font-mono text-sm text-gray-900">{{ $credentials['username'] }}</p>
                    </div>
                    <button type="button"
                            x-on:click="navigator.clipboard.writeText('{{ $credentials['username'] }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="ml-3 inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <span x-show="!copied">{{ __('driver.btn_copy') }}</span>
                        <span x-show="copied" x-cloak class="text-green-600">{{ __('driver.btn_copied') }}</span>
                    </button>
                </div>
            </div>

            {{-- Password --}}
            <div class="rounded-lg border border-gray-200 p-4" x-data="{ copied: false }">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium text-gray-500">{{ __('driver.field_owntracks_password_display') }}</span>
                        <p class="mt-1 font-mono text-sm text-gray-900">{{ $credentials['password'] }}</p>
                    </div>
                    <button type="button"
                            x-on:click="navigator.clipboard.writeText('{{ $credentials['password'] }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="ml-3 inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <span x-show="!copied">{{ __('driver.btn_copy') }}</span>
                        <span x-show="copied" x-cloak class="text-green-600">{{ __('driver.btn_copied') }}</span>
                    </button>
                </div>
            </div>

            {{-- Server URL --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <span class="text-sm font-medium text-gray-500">{{ __('driver.credentials_server_url') }}</span>
                <p class="mt-1 font-mono text-sm text-gray-900">{{ config('app.url') }}/api/owntracks</p>
            </div>

            {{-- Mode --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <span class="text-sm font-medium text-gray-500">{{ __('driver.credentials_mode') }}</span>
                <p class="mt-1 text-sm text-gray-900">HTTP</p>
            </div>
        </div>

        {{-- QR Code Section --}}
        <div class="mt-6 max-w-lg mx-auto">
            <h3 class="text-base font-semibold text-gray-900 mb-4">{{ __('driver.credentials_qr_heading') }}</h3>

            <div class="rounded-lg border border-gray-200 p-6 text-center"
                 x-data="{ qrUrl: '', copied: false }"
                 x-init="
                    qrUrl = generateOwntracksQr($refs.qrCanvas, {
                        serverUrl: {{ Js::from(rtrim(config('app.url'), '/') . '/api/owntracks') }},
                        username: {{ Js::from($credentials['username']) }},
                        password: {{ Js::from($credentials['password']) }}
                    });
                 ">
                <canvas x-ref="qrCanvas" class="mx-auto"></canvas>

                <p class="mt-4 text-sm text-gray-600">{{ __('driver.credentials_qr_instruction') }}</p>

                {{-- Config URL with copy button --}}
                <div class="mt-4 flex items-center gap-2">
                    <input type="text" readonly :value="qrUrl"
                           class="flex-1 rounded-md border-gray-300 bg-gray-50 text-xs font-mono text-gray-700 shadow-sm" />
                    <button type="button"
                            x-on:click="navigator.clipboard.writeText(qrUrl); copied = true; setTimeout(() => copied = false, 2000)"
                            class="shrink-0 inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <span x-show="!copied">{{ __('driver.credentials_qr_copy_url') }}</span>
                        <span x-show="copied" x-cloak class="text-green-600">{{ __('driver.credentials_qr_url_copied') }}</span>
                    </button>
                </div>
            </div>

            @if (str_starts_with(config('app.url'), 'http://'))
                <div class="mt-3 rounded-md bg-red-50 border border-red-200 p-3">
                    <div class="flex">
                        <div class="shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="ml-3 text-sm font-medium text-red-800">{{ __('driver.credentials_https_warning') }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Warning --}}
        <div class="mt-6 rounded-md bg-amber-50 border border-amber-200 p-4">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <p class="ml-3 text-sm font-medium text-amber-800">{{ __('driver.credentials_warning') }}</p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-4">
            <button type="button" x-data x-on:click="window.print()" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('driver.credentials_print') }}
            </button>
            <a href="{{ route('admin.drivers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('driver.credentials_back') }}</a>
        </div>
    </div>

    {{-- Print instruction (hidden on screen, shown in print) --}}
    <div class="print-instruction hidden mt-4 text-center text-sm text-gray-700">
        <p>{{ __('driver.credentials_print_instruction') }}</p>
    </div>

    {{-- Print-optimized CSS --}}
    <style>
        @media print {
            nav, aside, .sidebar, [x-data="{ sidebarOpen: false }"] > aside,
            [x-data="{ sidebarOpen: false }"] > div:first-child,
            button, a[href], input[type="text"] { display: none !important; }
            body { background: white !important; }
            .min-h-screen { min-height: auto !important; }
            .bg-gray-100 { background: white !important; }
            .shadow-sm { box-shadow: none !important; }
            .rounded-lg { border-radius: 0 !important; }
            .p-6 { padding: 1rem !important; }
            .mt-6 { margin-top: 0.5rem !important; }
            .font-mono { font-family: monospace !important; }
            canvas { display: block !important; }
            .print-instruction { display: block !important; }
        }
    </style>
</x-admin-layout>
