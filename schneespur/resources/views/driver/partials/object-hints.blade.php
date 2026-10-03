{{--
    Der Hinweis-Kasten der Fahrer-Ansicht. $source ist der Name eines
    Alpine-Getters der umgebenden Komponente (selectedHints | activeHints),
    der ein Element von allCustomerObjects oder null liefert. Der Kasten
    liest nur daraus — er braucht kein Netz.
--}}
<div
    x-show="hasHints({{ $source }})"
    x-data="{ open: {{ $open ? 'true' : 'false' }} }"
    data-object-hints="{{ $source }}"
    class="bg-gray-800 rounded-xl border border-amber-500/40"
>
    <button
        type="button"
        @click="open = !open"
        :aria-expanded="open"
        class="w-full min-h-[48px] px-4 py-3 flex items-center justify-between text-left text-base font-semibold text-amber-300"
    >
        <span>{{ __('driver.hints_title') }}</span>
        <span x-text="open ? '−' : '+'" aria-hidden="true"></span>
    </button>

    <div x-show="open" class="px-4 pb-4 space-y-3 text-sm text-gray-200">
        <p x-show="{{ $source }}?.salt_enabled === false" class="rounded-lg bg-red-900/60 px-3 py-2 font-semibold text-red-200">
            {{ __('driver.hints_no_salt') }}
        </p>
        <p x-show="{{ $source }}?.salt_enabled === true" class="text-gray-400">
            {{ __('driver.hints_salt_ok') }}
        </p>

        <div x-show="{{ $source }}?.access_notes">
            <p class="text-xs text-gray-400">{{ __('driver.hints_access') }}</p>
            <p class="whitespace-pre-line break-words" x-text="{{ $source }}?.access_notes"></p>
        </div>

        <div x-show="{{ $source }}?.site_notes">
            <p class="text-xs text-gray-400">{{ __('driver.hints_site') }}</p>
            <p class="whitespace-pre-line break-words" x-text="{{ $source }}?.site_notes"></p>
        </div>

        <div x-show="{{ $source }}?.plow_threshold">
            <p class="text-xs text-gray-400">{{ __('driver.hints_threshold') }}</p>
            <p x-text="{{ $source }}?.plow_threshold"></p>
        </div>
    </div>
</div>
