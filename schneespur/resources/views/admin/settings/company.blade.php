<x-admin-layout>
    <x-slot name="header">{{ __('settings.company_title') }} <x-help-icon topic="settings" /></x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.settings.company.update') }}" class="space-y-6">
            @csrf

            {{-- Firmenstammdaten --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('settings.company_description') }}</h3>

                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-700">{{ __('settings.company_name') }}</label>
                    <input type="text" name="company_name" id="company_name"
                           value="{{ old('company_name', $company_name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           maxlength="255" required>
                    @error('company_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="company_street" class="block text-sm font-medium text-gray-700">{{ __('settings.company_street') }}</label>
                    <input type="text" name="company_street" id="company_street"
                           value="{{ old('company_street', $company_street) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           maxlength="255">
                    @error('company_street')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="company_zip" class="block text-sm font-medium text-gray-700">{{ __('settings.company_zip') }}</label>
                        <input type="text" name="company_zip" id="company_zip"
                               value="{{ old('company_zip', $company_zip) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               maxlength="10">
                        @error('company_zip')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="col-span-2">
                        <label for="company_city" class="block text-sm font-medium text-gray-700">{{ __('settings.company_city') }}</label>
                        <input type="text" name="company_city" id="company_city"
                               value="{{ old('company_city', $company_city) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               maxlength="255">
                        @error('company_city')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="company_phone" class="block text-sm font-medium text-gray-700">{{ __('settings.company_phone') }}</label>
                        <input type="text" name="company_phone" id="company_phone"
                               value="{{ old('company_phone', $company_phone) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               maxlength="50">
                        @error('company_phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="company_email" class="block text-sm font-medium text-gray-700">{{ __('settings.company_email') }}</label>
                        <input type="email" name="company_email" id="company_email"
                               value="{{ old('company_email', $company_email) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               maxlength="255">
                        @error('company_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($company_lat && $company_lon)
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('settings.company_lat') }}</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $company_lat }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('settings.company_lon') }}</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $company_lon }}</p>
                        </div>
                    </div>
                @endif
                <p class="text-sm text-gray-500">{{ __('settings.company_geocode_manual_hint') }}</p>
            </div>

            {{-- Datenschutzbeauftragter --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('settings.dpo_title') }}</h3>
                <p class="text-sm text-gray-500">{{ __('settings.dpo_help') }}</p>

                <div>
                    <label for="dpo_contact" class="block text-sm font-medium text-gray-700">{{ __('settings.dpo_contact') }}</label>
                    <input type="text" name="dpo_contact" id="dpo_contact"
                           value="{{ old('dpo_contact', $dpo_contact) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           maxlength="255"
                           placeholder="{{ __('settings.dpo_contact_placeholder') }}">
                    @error('dpo_contact')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="dpo_email" class="block text-sm font-medium text-gray-700">{{ __('settings.dpo_email') }}</label>
                    <input type="email" name="dpo_email" id="dpo_email"
                           value="{{ old('dpo_email', $dpo_email) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           maxlength="255">
                    @error('dpo_email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Saisonzeitraum --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('settings.season_title') }}</h3>
                <p class="text-sm text-gray-500">{{ __('settings.season_help') }}</p>

                @php
                    $months = [
                        '01' => __('January'), '02' => __('February'), '03' => __('March'),
                        '04' => __('April'), '05' => __('May'), '06' => __('June'),
                        '07' => __('July'), '08' => __('August'), '09' => __('September'),
                        '10' => __('October'), '11' => __('November'), '12' => __('December'),
                    ];
                    $seasonFromMonth = substr(old('season_from', $season_from), 0, 2);
                    $seasonToMonth = substr(old('season_to', $season_to), 0, 2);
                @endphp

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="season_from" class="block text-sm font-medium text-gray-700">{{ __('settings.season_from') }}</label>
                        <select name="season_from" id="season_from"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach ($months as $num => $name)
                                <option value="{{ $num }}-01" @selected($seasonFromMonth === $num)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('season_from')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="season_to" class="block text-sm font-medium text-gray-700">{{ __('settings.season_to') }}</label>
                        <select name="season_to" id="season_to"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach ($months as $num => $name)
                                @php
                                    $lastDay = match ($num) {
                                        '02' => '28',
                                        '04', '06', '09', '11' => '30',
                                        default => '31',
                                    };
                                @endphp
                                <option value="{{ $num }}-{{ $lastDay }}" @selected($seasonToMonth === $num)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('season_to')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Alert-Schwellenwerte --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('settings.alert_title') }}</h3>

                <div>
                    <label for="alert_overdue_hours" class="block text-sm font-medium text-gray-700">{{ __('settings.alert_overdue_hours') }}</label>
                    <input type="number" name="alert_overdue_hours" id="alert_overdue_hours"
                           value="{{ old('alert_overdue_hours', $alert_overdue_hours) }}"
                           class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           min="1" required>
                    <p class="mt-1 text-sm text-gray-500">{{ __('settings.alert_overdue_hours_help') }}</p>
                    @error('alert_overdue_hours')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Sprache & Format --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('settings.locale_title') }}</h3>

                <div>
                    <label for="default_locale" class="block text-sm font-medium text-gray-700">{{ __('settings.locale_default') }}</label>
                    <select name="default_locale" id="default_locale"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected(old('default_locale', $default_locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-gray-500">{{ __('settings.locale_help') }}</p>
                    @error('default_locale')
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
    </div>
</x-admin-layout>
