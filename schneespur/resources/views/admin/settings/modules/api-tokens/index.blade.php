<x-admin-layout>
    <x-slot name="header">{{ __('modules.api_tokens_title') }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => __('modules.page_title'), 'url' => route('admin.settings.modules.index')],
        ['label' => $module->name ?? $module->slug],
        ['label' => __('modules.api_tokens_title')],
    ]" />

    <div class="mt-6 max-w-4xl" x-data="{ confirmRevoke: null }">
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

        {{-- Show plaintext token once --}}
        @if(session('plaintext_token'))
            <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-6" x-data="{ copied: false }">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-amber-800">{{ __('modules.token_show_once_title') }}</h3>
                        <p class="mt-1 text-sm text-amber-700">{{ __('modules.token_show_once_warning') }}</p>
                        <div class="mt-3 flex items-center gap-2">
                            <code class="flex-1 rounded-md border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-gray-900 select-all">{{ session('plaintext_token') }}</code>
                            <button type="button"
                                    x-on:click="navigator.clipboard.writeText('{{ session('plaintext_token') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="shrink-0 inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                <span x-show="!copied">{{ __('modules.token_copy') }}</span>
                                <span x-show="copied" x-cloak class="text-green-600">{{ __('modules.token_copied') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Header with create button --}}
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-600">{{ __('modules.api_tokens_description', ['name' => $module->name ?? $module->slug]) }}</p>
            <a href="{{ route('admin.settings.modules.api-tokens.create', $module->slug) }}"
               class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                {{ __('modules.token_btn_create') }}
            </a>
        </div>

        {{-- Token list --}}
        @if($tokens->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                </svg>
                <p class="mt-4 text-sm text-gray-500">{{ __('modules.no_tokens') }}</p>
            </div>
        @else
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.token_col_name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.token_col_created') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.token_col_last_used') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.token_col_expires') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($tokens as $token)
                            <tr class="{{ $token->isExpired() ? 'bg-gray-50 opacity-60' : '' }}">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $token->name }}
                                    @if($token->isExpired())
                                        <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('modules.token_expired') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $token->created_at->format('d.m.Y H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    {{ $token->last_used_at ? $token->last_used_at->format('d.m.Y H:i') : __('modules.token_never_used') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    {{ $token->expires_at ? $token->expires_at->format('d.m.Y H:i') : __('modules.token_no_expiry') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-800 font-medium"
                                        x-on:click="confirmRevoke = {{ $token->id }}"
                                    >
                                        {{ __('modules.token_btn_revoke') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Revoke Confirmation Dialog --}}
        <div
            x-show="confirmRevoke !== null"
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
                x-on:click.away="confirmRevoke = null"
                x-on:keydown.escape.window="confirmRevoke = null"
            >
                <h3 class="text-lg font-semibold text-gray-900">{{ __('modules.token_confirm_revoke_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('modules.token_confirm_revoke') }}</p>
                <div class="mt-4 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        x-on:click="confirmRevoke = null"
                    >
                        {{ __('modules.btn_cancel') }}
                    </button>
                    <form method="POST" x-bind:action="'{{ url('admin/settings/modules/' . $module->slug . '/api-tokens') }}/' + confirmRevoke">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">
                            {{ __('modules.token_btn_confirm_revoke') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
