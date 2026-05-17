<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md" x-data="{ copied: false }">
    <h3 class="text-sm font-semibold text-red-700 mb-2">{{ __('install.error_migration_main') }}</h3>

    <pre class="text-xs font-mono whitespace-pre-wrap bg-white border border-red-200 rounded p-3 max-h-48 overflow-y-auto mb-3">{{ $migrationOutput }}</pre>

    <p class="text-sm text-gray-600 mb-3">{{ __('install.error_migration_hint', ['app_name' => brand()]) }}</p>

    <div class="flex items-center gap-3">
        <button type="button"
            x-on:click="navigator.clipboard.writeText(@js($migrationOutput)); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition">
            <span x-show="!copied">{{ __('install.btn_copy_error') }}</span>
            <span x-show="copied" x-cloak>{{ __('install.migration_error_copied') }}</span>
        </button>

        <form method="POST" action="{{ route('install.migrations.run') }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white text-xs font-semibold rounded hover:bg-yellow-700 transition">
                {{ __('install.btn_retry_migration') }}
            </button>
        </form>
    </div>
</div>
