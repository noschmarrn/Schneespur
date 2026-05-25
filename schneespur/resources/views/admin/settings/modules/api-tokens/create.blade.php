<x-admin-layout>
    <x-slot name="header">{{ __('modules.token_create_title') }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => __('modules.page_title'), 'url' => route('admin.settings.modules.index')],
        ['label' => $module->name ?? $module->slug],
        ['label' => __('modules.api_tokens_title'), 'url' => route('admin.settings.modules.api-tokens.index', $module->slug)],
        ['label' => __('modules.token_create_title')],
    ]" />

    <div class="mt-6 max-w-lg">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('admin.settings.modules.api-tokens.store', $module->slug) }}">
                @csrf

                <div class="space-y-4">
                    {{-- Token Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('modules.token_field_name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                               placeholder="{{ __('modules.token_field_name_placeholder') }}">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Expiry --}}
                    <div>
                        <label for="expires_at" class="block text-sm font-medium text-gray-700">{{ __('modules.token_field_expires') }}</label>
                        <input type="datetime-local" name="expires_at" id="expires_at" value="{{ old('expires_at') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">{{ __('modules.token_field_expires_hint') }}</p>
                        @error('expires_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.settings.modules.api-tokens.index', $module->slug) }}" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ __('modules.btn_cancel') }}
                    </a>
                    <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                        {{ __('modules.token_btn_generate') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
