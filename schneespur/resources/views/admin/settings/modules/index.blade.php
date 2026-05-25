<x-admin-layout>
    <x-slot name="header">{{ __('modules.page_title') }}</x-slot>

    <div class="max-w-4xl" x-data="{ confirmRemove: null, trustFilter: '', confirmCommunityInstall: null }">
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

        {{-- Trust Level Filter --}}
        <div class="mb-6">
            <label for="trustFilter" class="block text-sm font-medium text-gray-700 mb-1">{{ __('modules.trust_filter_label') }}</label>
            <select id="trustFilter" x-model="trustFilter" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ __('modules.trust_filter_all') }}</option>
                <option value="official">{{ __('modules.trust_official') }}</option>
                <option value="verified">{{ __('modules.trust_verified') }}</option>
                <option value="community">{{ __('modules.trust_community') }}</option>
            </select>
        </div>

        {{-- Installed Modules --}}
        <section class="mb-8">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('modules.section_installed') }}</h2>

            @if($installedModules->isEmpty())
                <p class="text-sm text-gray-500">{{ __('modules.no_installed') }}</p>
            @else
                <div class="space-y-4">
                    @foreach($installedModules as $slug => $module)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" x-show="trustFilter === '' || trustFilter === '{{ $module['trust_level'] ?? '' }}'">
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
                                        @if(($module['signature_status'] ?? null) === 'verified')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800" title="{{ __('modules.signature_verified_tooltip') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                                                {{ __('modules.signature_verified') }}
                                            </span>
                                        @elseif(($module['signature_status'] ?? null) === 'unsigned')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800" title="{{ __('modules.signature_unsigned_tooltip') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                                {{ __('modules.signature_unsigned') }}
                                            </span>
                                        @elseif(($module['signature_status'] ?? null) === 'failed')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                                {{ __('modules.signature_failed_badge') }}
                                            </span>
                                        @endif
                                        @if(($module['trust_level'] ?? null) === 'official')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800" title="{{ __('modules.trust_official_tooltip') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                                                {{ __('modules.trust_official') }}
                                            </span>
                                        @elseif(($module['trust_level'] ?? null) === 'verified')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800" title="{{ __('modules.trust_verified_tooltip') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                {{ __('modules.trust_verified') }}
                                            </span>
                                        @elseif(($module['trust_level'] ?? null) === 'community')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800" title="{{ __('modules.trust_community_tooltip') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                                                {{ __('modules.trust_community') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600" title="{{ __('modules.trust_unknown_tooltip') }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
                                                {{ __('modules.trust_unknown') }}
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
                                    @if(!empty($module['requires_permissions']))
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($module['requires_permissions'] as $permission)
                                                <span class="inline-flex items-center rounded bg-purple-50 px-1.5 py-0.5 text-xs text-purple-600" title="{{ __('modules.permission_tooltip') }}">
                                                    <svg class="mr-0.5 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                    </svg>
                                                    {{ $permission }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
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
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" x-show="trustFilter === '' || trustFilter === '{{ $module['trust_level'] ?? '' }}'">
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
                                        @if(($module['signature_status'] ?? null) === 'signed')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800" title="{{ __('modules.signature_verified_tooltip') }}">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                                                {{ __('modules.signature_verified') }}
                                            </span>
                                        @elseif(($module['signature_status'] ?? null) === 'unsigned')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 font-medium text-yellow-800" title="{{ __('modules.signature_unsigned_tooltip') }}">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                                {{ __('modules.signature_unsigned') }}
                                            </span>
                                        @endif
                                        @if(($module['trust_level'] ?? null) === 'official')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-800" title="{{ __('modules.trust_official_tooltip') }}">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                                                {{ __('modules.trust_official') }}
                                            </span>
                                        @elseif(($module['trust_level'] ?? null) === 'verified')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800" title="{{ __('modules.trust_verified_tooltip') }}">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                {{ __('modules.trust_verified') }}
                                            </span>
                                        @elseif(($module['trust_level'] ?? null) === 'community')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 font-medium text-orange-800" title="{{ __('modules.trust_community_tooltip') }}">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                                                {{ __('modules.trust_community') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600" title="{{ __('modules.trust_unknown_tooltip') }}">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
                                                {{ __('modules.trust_unknown') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($module['requires_permissions']))
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($module['requires_permissions'] as $permission)
                                                <span class="inline-flex items-center rounded bg-purple-50 px-1.5 py-0.5 text-xs text-purple-600" title="{{ __('modules.permission_tooltip') }}">
                                                    <svg class="mr-0.5 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                    </svg>
                                                    {{ $permission }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3">
                                @if(($module['trust_level'] ?? null) === 'community')
                                    <button
                                        type="button"
                                        class="w-full rounded bg-blue-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-600"
                                        x-on:click="confirmCommunityInstall = '{{ $slug }}'"
                                    >
                                        {{ __('modules.btn_install') }}
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.settings.modules.install', $slug) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded bg-blue-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-600">
                                            {{ __('modules.btn_install') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Community Install Confirmation Dialog (Alpine.js) --}}
        <div
            x-show="confirmCommunityInstall !== null"
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
                x-on:click.away="confirmCommunityInstall = null"
                x-on:keydown.escape.window="confirmCommunityInstall = null"
            >
                <h3 class="text-lg font-semibold text-gray-900">{{ __('modules.trust_community') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('modules.trust_community_install_warning') }}</p>
                <div class="mt-4 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        x-on:click="confirmCommunityInstall = null"
                    >
                        {{ __('modules.btn_cancel') }}
                    </button>
                    <form method="POST" x-bind:action="'{{ url('admin/settings/modules') }}/' + confirmCommunityInstall + '/install'">
                        @csrf
                        <button type="submit" class="rounded bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600">
                            {{ __('modules.btn_install') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

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
