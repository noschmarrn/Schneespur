@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_5') }}</h2>

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            @foreach($errors->all() as $error)
                <p class="text-sm text-red-700">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('install.config.store') }}" x-data="{
        timezone: '{{ old('timezone', $timezone) }}',
        locale: '{{ old('locale', app()->getLocale()) }}',
        get brandName() {
            return this.locale === 'de' ? 'Schneespur' : 'Wintertrace';
        },
        init() {
            try {
                const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (detected) {
                    const options = [...this.$refs.tzSelect.options].map(o => o.value);
                    if (options.includes(detected)) {
                        this.timezone = detected;
                    }
                }
            } catch (e) {}
        }
    }">
        @csrf
        <div class="space-y-4">
            <div>
                <x-input-label for="app_url" :value="__('install.config_url_label')" />
                <x-text-input id="app_url" name="app_url" type="url" class="mt-1 block w-full" :value="old('app_url', $app_url)" required />
                <p class="mt-1 text-xs text-gray-500">{{ __('install.config_url_help', ['app_name' => brand()]) }}</p>
                <x-input-error :messages="$errors->get('app_url')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="timezone" :value="__('install.config_tz_label')" />
                <select name="timezone" id="timezone" x-ref="tzSelect" x-model="timezone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="Europe/Berlin">Europe/Berlin</option>
                    <option value="Europe/Vienna">Europe/Vienna</option>
                    <option value="Europe/Zurich">Europe/Zurich</option>
                    <option value="Europe/London">Europe/London</option>
                    <option value="Europe/Paris">Europe/Paris</option>
                    <option value="UTC">UTC</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('install.config_tz_detected') }}</p>
                <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="locale" :value="__('install.config_locale_label')" />
                <select name="locale" id="locale" x-model="locale" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="de">Deutsch</option>
                    <option value="en">English</option>
                </select>
                <p class="mt-1 text-xs text-gray-500" x-text="'{{ __('install.config_brand_hint') }}'.replace(':brand', brandName)"></p>
                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <a href="{{ route('install.migrations') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <x-primary-button>
                {{ __('install.config_submit_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>
@endsection
