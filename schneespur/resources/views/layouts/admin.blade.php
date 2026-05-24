<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ brand() }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
        @extensionSlot('admin.head.after')
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen flex bg-gray-100">

            {{-- Mobile overlay --}}
            <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-300"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-30 bg-black/50 lg:hidden"
                 @click="sidebarOpen = false"
                 style="display: none;"></div>

            {{-- Sidebar --}}
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                   class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-800 text-gray-100 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:z-auto flex flex-col">

                {{-- Logo --}}
                <div class="flex items-center h-16 px-6 border-b border-gray-700">
                    <span class="text-xl font-bold tracking-wide text-white">{{ brand() }}</span>
                </div>

                @extensionSlot('admin.sidebar.before-nav')

                {{-- Navigation (registry-driven) --}}
                @php
                    $navRegistry = app(\App\Services\Extension\NavigationRegistry::class);
                    $navGroups = $navRegistry->getGroups();
                    $navItems = $navRegistry->getItems(auth()->user());
                @endphp
                <nav class="flex-1 overflow-y-auto py-4" aria-label="{{ __('admin.nav_main_aria') }}">
                    @foreach($navGroups as $group)
                        @php
                            $groupItems = $navItems[$group['key']] ?? [];
                            $visibleItems = array_filter($groupItems, function ($item) {
                                return empty($item['route_check']) || Route::has($item['route_check']);
                            });
                        @endphp
                        @if(count($visibleItems) > 0)
                            @if($group['label'] !== '')
                            <div class="mt-4 mb-1 px-6">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $group['label'] }}</span>
                            </div>
                            @endif
                            @foreach($visibleItems as $item)
                                @php
                                    $pattern = $item['active_pattern'];
                                    if (str_contains($pattern, '&!')) {
                                        [$include, $exclude] = explode('&!', $pattern, 2);
                                        $isActive = request()->routeIs($include) && !request()->routeIs($exclude);
                                    } else {
                                        $isActive = request()->routeIs($pattern);
                                    }
                                    $isFirst = $group['key'] === 'top';
                                    $pyClass = $isFirst ? 'py-3' : 'py-2.5';
                                @endphp
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center px-6 {{ $pyClass }} text-sm font-medium transition-colors {{ $isActive ? 'bg-gray-900 text-white border-l-4 border-blue-500 pl-5' : 'hover:bg-gray-700' }}">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        @foreach(explode('||', $item['icon']) as $path)
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                                        @endforeach
                                    </svg>
                                    {{ $item['label'] }}
                                    @if($item['badge'])
                                        @php $badgeVar = $item['badge']; $badgeValue = $$badgeVar ?? 0; @endphp
                                        @if($badgeValue > 0)
                                        <span class="ml-auto inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">{{ $badgeValue }}</span>
                                        @endif
                                    @endif
                                </a>
                            @endforeach
                        @endif
                    @endforeach
                </nav>

                @extensionSlot('admin.sidebar.after-nav')
            </aside>

            {{-- Main content area --}}
            <div class="flex-1 flex flex-col min-w-0">
                {{-- Top bar --}}
                <header class="flex items-center justify-between h-16 px-4 bg-white border-b border-gray-200 sm:px-6 lg:px-8">
                    {{-- Mobile hamburger --}}
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path x-show="!sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" style="display: none;" />
                        </svg>
                    </button>

                    {{-- Page heading --}}
                    <h1 class="text-lg font-semibold text-gray-800 lg:ml-0">
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </h1>

                    {{-- User dropdown --}}
                    <div class="flex items-center">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                    <div>{{ Auth::user()->name }}</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('admin.nav_profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('admin.nav_logout') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>

                {{-- Page content --}}
                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    @extensionSlot('admin.content.before')
                    <x-flash-message />
                    {{ $slot }}

                    @extensionSlot('admin.content.after')

                    <footer class="mt-12 pt-4 border-t border-gray-200 text-xs text-gray-400">
                        {{ brand() }} {{ config('app.version', '1.0') }} &middot;
                        <a href="{{ route('admin.help.index') }}" class="hover:text-gray-600">{{ __('admin.footer_help') }}</a>
                    </footer>
                </main>
            </div>
        </div>
    </body>
</html>
