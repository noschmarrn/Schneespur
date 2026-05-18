<x-admin-layout>
    <x-slot name="header">{{ __('weather.settings_title') }}</x-slot>

    <div class="max-w-2xl" x-data="{
        provider: '{{ old('weather_provider', $activeProvider) }}',
        providers: {{ Js::from($providers) }},
        get requiresApiKey() { return this.providers[this.provider]?.requires_api_key ?? false },
        get isMetNorway() { return this.provider === 'met_norway' },
        get isFree() { return this.provider === 'openmeteo_free' },
        testing: false,
        testResult: null,
        async testConnection() {
            this.testing = true;
            this.testResult = null;
            try {
                const resp = await fetch('{{ route('admin.settings.weather.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ provider: this.provider }),
                });
                this.testResult = await resp.json();
            } catch (e) {
                this.testResult = { ok: false, message: e.message, latency_ms: 0 };
            }
            this.testing = false;
        }
    }">

        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.weather.update') }}" class="space-y-6">
            @csrf

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('weather.settings_description') }}</h3>

                <div>
                    <label for="weather_provider" class="block text-sm font-medium text-gray-700">{{ __('weather.settings_provider') }}</label>
                    <select name="weather_provider" id="weather_provider" x-model="provider"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @foreach($providers as $slug => $info)
                            <option value="{{ $slug }}">{{ $info['name'] }}</option>
                        @endforeach
                    </select>
                    @error('weather_provider')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="isFree" x-cloak class="rounded-md border border-yellow-200 bg-yellow-50 p-4">
                    <p class="text-sm text-yellow-800">{{ __('weather.provider_free_warning') }}</p>
                </div>

                <div x-show="requiresApiKey" x-cloak>
                    <label for="weather_api_key" class="block text-sm font-medium text-gray-700">{{ __('weather.settings_api_key') }}</label>
                    <input type="password" name="weather_api_key" id="weather_api_key"
                           value="{{ old('weather_api_key', $apiKey) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           maxlength="255">
                    <p class="mt-1 text-sm text-gray-500">{{ __('weather.settings_api_key_help') }}</p>
                    @error('weather_api_key')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="isMetNorway" x-cloak>
                    <label for="weather_user_agent_email" class="block text-sm font-medium text-gray-700">{{ __('weather.settings_user_agent_email') }}</label>
                    <input type="email" name="weather_user_agent_email" id="weather_user_agent_email"
                           value="{{ old('weather_user_agent_email', $userAgentEmail) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           maxlength="255">
                    <p class="mt-1 text-sm text-gray-500">{{ __('weather.settings_user_agent_email_help') }}</p>
                    @error('weather_user_agent_email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="weather_cache_ttl" class="block text-sm font-medium text-gray-700">{{ __('weather.settings_cache_ttl') }}</label>
                    <input type="number" name="weather_cache_ttl" id="weather_cache_ttl"
                           value="{{ old('weather_cache_ttl', $cacheTtlMinutes) }}"
                           class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           min="1" required>
                    <p class="mt-1 text-sm text-gray-500">{{ __('weather.settings_cache_ttl_help') }}</p>
                    @error('weather_cache_ttl')
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

        <div class="bg-white shadow-sm rounded-lg p-6 space-y-4 mt-6">
            <h3 class="text-lg font-medium text-gray-900">{{ __('weather.settings_test_section') }}</h3>
            <p class="text-sm text-gray-500">{{ __('weather.settings_test_help') }}</p>

            <button type="button" @click="testConnection()" :disabled="testing"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150">
                <svg x-show="testing" x-cloak class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                {{ __('weather.settings_test_btn') }}
            </button>

            <div x-show="testResult" x-cloak>
                <template x-if="testResult?.ok">
                    <div class="rounded-md bg-green-50 p-4">
                        <p class="text-sm text-green-800" x-text="'{{ __('weather.settings_test_success') }}'.replace(':latency', testResult.latency_ms)"></p>
                    </div>
                </template>
                <template x-if="testResult && !testResult.ok">
                    <div class="rounded-md bg-red-50 p-4">
                        <p class="text-sm text-red-800" x-text="'{{ __('weather.settings_test_failure') }}'.replace(':message', testResult.message)"></p>
                    </div>
                </template>
            </div>
        </div>

    </div>
</x-admin-layout>
