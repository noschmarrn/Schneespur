<x-admin-layout>
    <x-slot name="header">{{ __('modules.page_title') }}</x-slot>

    <div class="max-w-4xl" x-data="{ confirmRemove: null }">
        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if($catalogError)
            <div class="mb-4 rounded-md bg-yellow-50 p-4 text-sm text-yellow-700">
                {{ __('modules.catalog_error_notice') }}: {{ $catalogError }}
            </div>
        @endif

        @php
            $installedModules = collect($modules)->filter(fn($m) => $m['installed']);
            $availableModules = collect($modules)->filter(fn($m) => !$m['installed']);
        @endphp

        {{-- Installed Modules --}}
        <section class="mb-8">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('modules.section_installed') }}</h2>

            @if($installedModules->isEmpty())
                <p class="text-sm text-gray-500">{{ __('modules.no_installed') }}</p>
            @else
                <div class="space-y-4">
                    @foreach($installedModules as $slug => $module)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-4">
                                {{-- Module Icon/Image --}}
                                <div class="flex-shrink-0">
                                    @if(!empty($module['image']))
                                        <img src="{{ $module['image'] }}" alt="" class="h-12 w-12 rounded-lg object-cover">
                                    @else
                                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875S10.5 3.09 10.5 4.125c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.421 48.421 0 0 1-4.185-.408.64.64 0 0 1-.544-.575c-.069-.595-.234-1.143-.52-1.622C4.813 2.97 3.532 2.25 2.062 2.25A.75.75 0 0 0 1.312 3c0 1.47.721 2.751 1.875 3.282.48.286 1.027.451 1.622.52a.636.636 0 0 1 .575.544 48.421 48.421 0 0 1 .408 4.185.64.64 0 0 1-.643.657v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0a.64.64 0 0 1 .643.657 48.421 48.421 0 0 1-.408 4.185.636.636 0 0 1-.575.544c-.595.069-1.143.234-1.622.52C2.033 21.187 1.312 22.468 1.312 23.938a.75.75 0 0 0 .75.75c1.47 0 2.751-.721 3.282-1.875.286-.48.451-1.027.52-1.622a.636.636 0 0 1 .544-.575 48.421 48.421 0 0 1 4.185-.408.64.64 0 0 1 .657.643v0c0 .355-.186.676-.401.959a1.647 1.647 0 0 0-.349 1.003c0 1.036 1.007 1.875 2.25 1.875s2.25-.84 2.25-1.875c0-.369-.128-.713-.349-1.003-.215-.283-.401-.604-.401-.959v0a.64.64 0 0 1 .643-.657 48.421 48.421 0 0 1 4.185.408.636.636 0 0 1 .544.575c.069.595.234 1.143.52 1.622.531 1.154 1.812 1.875 3.282 1.875a.75.75 0 0 0 .75-.75c0-1.47-.721-2.751-1.875-3.282a4.073 4.073 0 0 0-1.622-.52.636.636 0 0 1-.575-.544 48.421 48.421 0 0 1-.408-4.185.64.64 0 0 1 .657-.643v0c.355 0 .676.186.959.401.29.221.634.349 1.003.349 1.036 0 1.875-1.007 1.875-2.25s-.84-2.25-1.875-2.25c-.369 0-.713.128-1.003.349-.283.215-.604.401-.959.401v0a.64.64 0 0 1-.657-.643 48.421 48.421 0 0 1 .408-4.185.636.636 0 0 1 .575-.544c.595-.069 1.143-.234 1.622-.52C21.967 6.033 22.688 4.752 22.688 3.282a.75.75 0 0 0-.75-.75c-1.47 0-2.751.721-3.282 1.875a4.073 4.073 0 0 0-.52 1.622.636.636 0 0 1-.544.575 48.421 48.421 0 0 1-4.185.408.64.64 0 0 1-.657-.643Z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                {{-- Module Info --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-semibold text-gray-900">{{ $module['name'] }}</h3>
                                        @if($module['is_orphan'])
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" title="{{ __('modules.orphan_tooltip') }}">
                                                {{ __('modules.orphan_badge') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600">{{ $module['description'] }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-blue-700">
                                            v{{ $module['installed_version'] }}
                                        </span>
                                        @if($module['enabled'])
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-green-700">
                                                {{ __('modules.status_enabled') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">
                                                {{ __('modules.status_disabled') }}
                                            </span>
                                        @endif
                                        @if($module['has_update'])
                                            <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-orange-700">
                                                {{ __('modules.update_available', ['version' => $module['catalog_version']]) }}
                                            </span>
                                        @endif
                                        @if(!empty($module['category']))
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-600">
                                                {{ $module['category'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex flex-shrink-0 items-center gap-2">
                                    @if($module['has_update'])
                                        <form method="POST" action="{{ route('admin.settings.modules.update', $slug) }}">
                                            @csrf
                                            <button type="submit" class="rounded bg-orange-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-orange-600">
                                                {{ __('modules.btn_update') }}
                                            </button>
                                        </form>
                                    @endif

                                    @if($module['enabled'])
                                        <form method="POST" action="{{ route('admin.settings.modules.disable', $slug) }}">
                                            @csrf
                                            <button type="submit" class="rounded bg-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-300">
                                                {{ __('modules.btn_disable') }}
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.settings.modules.enable', $slug) }}">
                                            @csrf
                                            <button type="submit" class="rounded bg-green-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-600">
                                                {{ __('modules.btn_enable') }}
                                            </button>
                                        </form>
                                    @endif

                                    <button
                                        type="button"
                                        class="rounded bg-red-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-600"
                                        x-on:click="confirmRemove = '{{ $slug }}'"
                                    >
                                        {{ __('modules.btn_remove') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Available Modules --}}
        <section>
            <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('modules.section_available') }}</h2>

            @if($availableModules->isEmpty())
                <p class="text-sm text-gray-500">{{ __('modules.no_available') }}</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($availableModules as $slug => $module)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-3">
                                {{-- Module Icon/Image --}}
                                <div class="flex-shrink-0">
                                    @if(!empty($module['image']))
                                        <img src="{{ $module['image'] }}" alt="" class="h-10 w-10 rounded-lg object-cover">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $module['name'] }}</h3>
                                    <p class="mt-1 text-xs text-gray-600 line-clamp-2">{{ $module['description'] }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        @if($module['catalog_version'])
                                            <span class="text-gray-400">v{{ $module['catalog_version'] }}</span>
                                        @endif
                                        @if(!empty($module['category']))
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-600">
                                                {{ $module['category'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <form method="POST" action="{{ route('admin.settings.modules.install', $slug) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded bg-blue-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-600">
                                        {{ __('modules.btn_install') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Remove Confirmation Dialog (Alpine.js) --}}
        <div
            x-show="confirmRemove !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div
                class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
                x-on:click.away="confirmRemove = null"
                x-on:keydown.escape.window="confirmRemove = null"
            >
                <h3 class="text-lg font-semibold text-gray-900">{{ __('modules.confirm_remove_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('modules.confirm_remove') }}</p>
                <div class="mt-4 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        x-on:click="confirmRemove = null"
                    >
                        {{ __('modules.btn_cancel') }}
                    </button>
                    <form method="POST" x-bind:action="'{{ url('admin/settings/modules') }}/' + confirmRemove + '/remove'">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">
                            {{ __('modules.btn_confirm_remove') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
