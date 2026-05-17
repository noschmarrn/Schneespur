<div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md" x-data="{ copied: false }">
    <p class="text-sm font-semibold text-yellow-800 mb-2">{{ __('install.error_env_write') }}</p>
    <p class="text-sm text-yellow-700 mb-3">{{ __('install.env_fallback_instructions', ['app_name' => brand()]) }}</p>

    <textarea readonly class="w-full h-40 font-mono text-xs p-2 border border-yellow-300 rounded bg-white">{{ $envContent }}</textarea>

    <div class="flex items-center gap-3 mt-3">
        <button type="button"
            x-on:click="navigator.clipboard.writeText($refs.envContent?.value ?? '{{ addslashes($envContent) }}'); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white text-xs font-semibold rounded hover:bg-yellow-700 transition">
            <span x-show="!copied">{{ __('install.env_fallback_copy_btn') }}</span>
            <span x-show="copied" x-cloak>{{ __('install.env_fallback_copied') }}</span>
        </button>

        <a href="{{ url()->current() }}" class="text-sm text-yellow-700 underline hover:text-yellow-900">
            {{ __('install.env_fallback_recheck') }}
        </a>
    </div>
</div>
