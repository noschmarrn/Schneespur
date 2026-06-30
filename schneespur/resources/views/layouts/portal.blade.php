<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="@yield('robots', 'noindex, nofollow')">

        <title>{{ __('portal.title') }} &mdash; {{ brand() }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @extensionSlot('portal.head.after')
        @moduleAssets
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col bg-gray-50">

            {{-- Top navigation --}}
            <header class="bg-white border-b border-gray-200" x-data="{ open: false }">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center space-x-6">
                            <a href="{{ route('portal.home') }}" class="text-xl font-bold text-gray-800 tracking-wide">
                                {{ brand() }}
                            </a>
                            @php
                                $portalNav = app(\App\Services\Extension\PortalNavigationRegistry::class);
                                $portalNavItems = $portalNav->getItems(auth('customer')->user());
                            @endphp
                            <nav class="hidden sm:flex space-x-4">
                                @foreach($portalNavItems as $item)
                                    <a href="{{ route($item['route']) }}"
                                       class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($item['active_pattern']) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </nav>
                            @extensionSlot('portal.nav.after')
                        </div>

                        <div class="hidden sm:flex items-center space-x-4">
                            <span class="text-sm text-gray-600">{{ auth('customer')->user()->name }}</span>
                            <form method="POST" action="{{ route('portal.logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
                                    {{ __('portal.logout') }}
                                </button>
                            </form>
                        </div>

                        {{-- Mobile menu button --}}
                        <button @click="open = !open" class="sm:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" style="display: none;" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Mobile menu --}}
                <div x-show="open" x-transition class="sm:hidden border-t border-gray-200" style="display: none;">
                    <div class="px-4 py-3 space-y-1">
                        @foreach($portalNavItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs($item['active_pattern']) ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                    <div class="border-t border-gray-200 px-4 py-3">
                        <div class="text-sm text-gray-600 mb-2">{{ auth('customer')->user()->name }}</div>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50">
                                {{ __('portal.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 py-8">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    @extensionSlot('portal.content.before')
                    <x-flash-message />
                    {{ $slot }}
                    @extensionSlot('portal.content.after')
                </div>
            </main>

            {{-- Footer --}}
            <footer class="border-t border-gray-200 bg-white">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    @extensionSlot('portal.footer.before')
                    <p class="text-xs text-gray-400 text-center">
                        {{ brand() }} &middot; {{ __('portal.footer_portal') }}
                    </p>
                </div>
            </footer>
        </div>
    </body>
</html>
