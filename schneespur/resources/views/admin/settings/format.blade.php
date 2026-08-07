<x-admin-layout>
    <x-slot name="header">{{ __('format.settings_title') }}</x-slot>

    <div class="max-w-2xl">

        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        {{-- 1. Region template: its own form, so "apply" cannot be confused with "save" --}}
        <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
            <h3 class="text-lg font-medium text-gray-900">{{ __('format.settings_description') }}</h3>

            <form method="POST" action="{{ route('admin.settings.format.update') }}">
                @csrf

                <label for="apply_region" class="block text-sm font-medium text-gray-700">{{ __('format.field_region') }}</label>
                <select name="apply_region" id="apply_region"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @foreach($regions as $code => $label)
                        <option value="{{ $code }}" @selected($code === $activeRegion)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('apply_region')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-4">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('format.apply_region') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- 2. Individual fields --}}
        <form method="POST" action="{{ route('admin.settings.format.update') }}" class="space-y-6 mt-6">
            @csrf

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                @foreach ([
                    'date_format' => ['dmy_dot', 'dmy_slash', 'mdy_slash', 'iso'],
                    'time_format' => ['24h', '12h'],
                    'number_format' => ['dot_comma', 'comma_dot', 'apostrophe_dot'],
                    'currency_position' => ['before', 'after'],
                    'unit_distance' => ['metric', 'imperial'],
                    'unit_temperature' => ['celsius', 'fahrenheit'],
                    'unit_weight' => ['metric', 'imperial'],
                ] as $field => $options)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-gray-700">{{ __('format.field_' . $field) }}</label>
                        <select name="{{ $field }}" id="{{ $field }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($options as $option)
                                <option value="{{ $option }}" @selected(old($field, $current[$field] ?? null) === $option)>
                                    {{ __('format.option_' . $field . '_' . $option) }}
                                </option>
                            @endforeach
                        </select>
                        @error($field)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div>
                    <label for="currency_code" class="block text-sm font-medium text-gray-700">{{ __('format.field_currency_code') }}</label>
                    <input type="text" name="currency_code" id="currency_code" maxlength="3"
                           value="{{ old('currency_code', $current['currency_code'] ?? 'EUR') }}"
                           class="mt-1 block w-32 uppercase rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('currency_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('settings.button_save') }}
                </button>
            </div>
        </form>

        {{-- 3. Preview of what is stored right now. Without it the operator
             has to guess what "mdy_slash" means. --}}
        <div class="bg-white shadow-sm rounded-lg p-6 space-y-4 mt-6">
            <h3 class="text-lg font-medium text-gray-900">{{ __('format.preview') }}</h3>
            <dl class="grid grid-cols-2 gap-2 text-sm">
                <dt class="text-gray-500">{{ __('format.field_date_format') }}</dt>
                <dd class="text-gray-900">{{ format_datetime(now()) }}</dd>
                <dt class="text-gray-500">{{ __('format.field_currency_code') }}</dt>
                <dd class="text-gray-900">{{ format_money(123456) }}</dd>
                <dt class="text-gray-500">{{ __('format.field_unit_temperature') }}</dt>
                <dd class="text-gray-900">{{ format_temperature(-4.0) }}</dd>
                <dt class="text-gray-500">{{ __('format.field_unit_distance') }}</dt>
                <dd class="text-gray-900">{{ format_distance(2500.0) }} &middot; {{ format_speed(12.5) }}</dd>
                <dt class="text-gray-500">{{ __('format.field_unit_weight') }}</dt>
                <dd class="text-gray-900">{{ format_weight(1500.0) }}</dd>
            </dl>
        </div>

    </div>
</x-admin-layout>
