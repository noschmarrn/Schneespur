<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="@yield('robots', 'noindex, nofollow')">

        <title>{{ brand() }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <meta name="theme-color" content="#111827">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ brand() }}">
        <link rel="apple-touch-icon" href="/pwa-icon-192x192.png">
        <link rel="manifest" href="/manifest.webmanifest">

        @extensionSlot('driver.head.after')
        @moduleAssets

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @include('partials.format-settings')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-900 text-gray-100">
        <div class="min-h-screen flex flex-col">
            {{-- Compact top bar --}}
            <nav class="bg-gray-800 border-b border-gray-700">
                <div class="px-4 safe-top">
                    <div class="flex items-center justify-between h-14">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-wide text-white hover:text-gray-200 transition">{{ brand() }}</a>
                            <span data-shift-badge class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ (isset($shiftStatus) && $shiftStatus === 'active') ? 'bg-green-900 text-green-300' : 'bg-gray-700 text-gray-400' }}">
                                <span data-shift-dot class="h-1.5 w-1.5 rounded-full {{ (isset($shiftStatus) && $shiftStatus === 'active') ? 'bg-green-400' : 'bg-gray-500' }}"></span>
                                <span data-shift-text>{{ (isset($shiftStatus) && $shiftStatus === 'active') ? __('driver.shift_active') : __('driver.shift_inactive') }}</span>
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            @extensionSlot('driver.topbar.actions')

                            <div x-data="connectivityIndicator()" class="flex items-center gap-1">
                                <span class="relative flex h-2 w-2">
                                    <template x-if="state === 'syncing'">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    </template>
                                    <span class="relative inline-flex rounded-full h-2 w-2"
                                          :class="{
                                              'bg-green-400': state === 'online',
                                              'bg-red-400': state === 'offline',
                                              'bg-amber-400': state === 'syncing'
                                          }"></span>
                                </span>
                                <span x-show="pendingCount > 0" x-cloak
                                      class="inline-flex items-center justify-center rounded-full bg-amber-600 text-white text-[10px] font-bold leading-none px-1.5 py-0.5"
                                      x-text="pendingCount"></span>
                                <button x-show="failedCount > 0" x-cloak
                                        @click="retry()"
                                        type="button"
                                        :aria-label="failedAriaLabel"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold leading-none text-white min-h-[44px] sm:min-h-0">
                                    <span x-text="failedCount"></span>
                                    <span>{{ __('driver.connectivity_retry') }}</span>
                                </button>
                            </div>

                            <a href="{{ route('driver.jobs.index') }}"
                               class="min-h-[44px] min-w-[44px] flex items-center justify-center {{ request()->routeIs('driver.jobs.*') ? 'text-blue-400' : 'text-gray-400 hover:text-gray-200' }} transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </a>

                            <span class="hidden sm:inline text-sm text-gray-400 truncate max-w-[100px]">{{ Auth::user()->name }}</span>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="min-h-[44px] min-w-[44px] flex items-center justify-center text-gray-400 hover:text-gray-200 transition" title="{{ __('driver.nav_logout') }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            {{-- Page content --}}
            <main class="flex-1 px-4 py-4 pb-24">
                @extensionSlot('driver.content.before')
                <x-driver-flash-message />
                {{ $slot }}
                @extensionSlot('driver.content.after')
            </main>

            {{-- Bottom nav area for main actions --}}
            @isset($bottomNav)
                <div class="fixed bottom-0 inset-x-0 bg-gray-800 border-t border-gray-700 safe-bottom">
                    <div class="px-4 py-3">
                        {{ $bottomNav }}
                    </div>
                </div>
            @endisset
            @extensionSlot('driver.bottom-nav.after')
        </div>
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js');
                });
            }

            document.addEventListener('alpine:init', () => {
                Alpine.data('connectivityIndicator', () => ({
                    state: navigator.onLine ? 'online' : 'offline',
                    pendingCount: 0,
                    failedCount: 0,
                    labels: {
                        online: @json(__('driver.connectivity_online')),
                        offline: @json(__('driver.connectivity_offline')),
                        syncing: @json(__('driver.connectivity_syncing')),
                    },
                    // Raw string, ':count' placeholder left in place — this
                    // number only exists client-side, so the substitution
                    // happens in the getter below rather than in __().
                    failedCountLabel: @json(__('driver.connectivity_failed_count')),
                    get label() {
                        return this.labels[this.state] || this.labels.online;
                    },
                    get failedAriaLabel() {
                        return this.failedCountLabel.replace(':count', this.failedCount);
                    },
                    async init() {
                        window.addEventListener('online', () => {
                            if (this.state !== 'syncing') this.state = 'online';
                        });
                        window.addEventListener('offline', () => {
                            this.state = 'offline';
                        });
                        window.addEventListener('sync:start', (e) => {
                            this.state = 'syncing';
                            if (e.detail && e.detail.count != null) {
                                this.pendingCount = e.detail.count;
                            }
                        });
                        window.addEventListener('sync:complete', () => {
                            this.state = navigator.onLine ? 'online' : 'offline';
                            this.refreshCounts();
                        });
                        window.addEventListener('sync:error', () => {
                            this.state = navigator.onLine ? 'online' : 'offline';
                        });

                        // A page load with no sync run yet must still show a
                        // failed entry saved on a previous visit. Wait for the
                        // queue's IndexedDB connection to actually open first —
                        // failedCount() throws before that.
                        if (window.foregroundSync && window.foregroundSync.ready) {
                            try {
                                await window.foregroundSync.ready;
                            } catch {
                                // IndexedDB unavailable (e.g. Firefox private
                                // browsing) — nothing more to read, badges
                                // stay at their initial values.
                                return;
                            }
                        }
                        await this.refreshCounts();
                    },
                    // The single source of truth for both counters: always a
                    // fresh read of the queue, never a value carried in an
                    // event payload. sync:start/sync:complete are only hints
                    // that now is a good time to re-read it, not values to
                    // trust as-is — several flush() paths (offline, a sync
                    // already in flight, nothing pending) return without
                    // dispatching anything at all, and offline page loads
                    // never see a sync event at all.
                    async refreshCounts() {
                        const queue = window.foregroundSync && window.foregroundSync.queue;
                        if (!queue || !queue.db) return;
                        try {
                            this.failedCount = await queue.failedCount();
                            this.pendingCount = await queue.getCount();
                        } catch {
                            // Leave the previous values — an error here means
                            // "unknown", not "zero".
                        }
                    },
                    async retry() {
                        if (!window.foregroundSync) return;
                        try {
                            await window.foregroundSync.queue.retryFailed();
                            await this.refreshCounts();
                            await window.foregroundSync.flush();
                        } catch {
                            // Tapping retry while offline or mid-sync must not
                            // surface a console error to the driver — the
                            // counts above already reflect reality.
                        }
                    },
                }));
            });
        </script>
    </body>
</html>
