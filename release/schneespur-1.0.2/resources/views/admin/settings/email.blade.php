<x-admin-layout>
    <x-slot name="header">{{ __('notification.page_email_settings') }} <x-help-icon topic="settings" /></x-slot>

    <div class="max-w-2xl">
        @unless ($envWritable)
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md" x-data="{ copied: false }">
                <p class="text-sm font-semibold text-yellow-800 mb-2">{{ __('notification.env_not_writable') }}</p>
                <p class="text-sm text-yellow-700 mb-3">{{ __('notification.env_copy_instructions') }}</p>

                <textarea readonly
                    x-ref="envContent"
                    class="w-full h-40 font-mono text-xs p-2 border border-yellow-300 rounded bg-white">{{ $envContent }}</textarea>

                <div class="flex items-center gap-3 mt-3">
                    <button type="button"
                        x-on:click="navigator.clipboard.writeText($refs.envContent.value); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white text-xs font-semibold rounded hover:bg-yellow-700 transition">
                        <span x-show="!copied">{{ __('notification.env_copy_btn') }}</span>
                        <span x-show="copied" x-cloak>{{ __('notification.env_copied') }}</span>
                    </button>

                    <a href="{{ url()->current() }}" class="text-sm text-yellow-700 underline hover:text-yellow-900">
                        {{ __('notification.env_recheck') }}
                    </a>
                </div>
            </div>
        @endunless

        <form method="POST" action="{{ route('admin.settings.email.update') }}" class="space-y-6">
            @csrf

            {{-- SMTP Configuration --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('notification.smtp_section') }}</h3>

                <div>
                    <label for="mail_mailer" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_mailer') }}</label>
                    <select name="mail_mailer" id="mail_mailer"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="smtp" @selected(old('mail_mailer', $config['MAIL_MAILER']) === 'smtp')>SMTP</option>
                        <option value="sendmail" @selected(old('mail_mailer', $config['MAIL_MAILER']) === 'sendmail')>Sendmail</option>
                        <option value="log" @selected(old('mail_mailer', $config['MAIL_MAILER']) === 'log')>Log (nur Entwicklung)</option>
                    </select>
                    @error('mail_mailer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_host" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_host') }}</label>
                    <input type="text" name="mail_host" id="mail_host"
                        value="{{ old('mail_host', $config['MAIL_HOST']) }}"
                        placeholder="smtp.example.com"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-sm text-gray-500">{{ __('notification.hint_mail_host') }}</p>
                    @error('mail_host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_port" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_port') }}</label>
                    <input type="number" name="mail_port" id="mail_port"
                        value="{{ old('mail_port', $config['MAIL_PORT']) }}"
                        min="1" max="65535"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-sm text-gray-500">{{ __('notification.hint_mail_port') }}</p>
                    @error('mail_port') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_scheme" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_scheme') }}</label>
                    <select name="mail_scheme" id="mail_scheme"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="null" @selected(in_array(old('mail_scheme', $config['MAIL_SCHEME']), ['', 'null'], true))>{{ __('notification.scheme_auto') }}</option>
                        <option value="tls" @selected(old('mail_scheme', $config['MAIL_SCHEME']) === 'tls')>{{ __('notification.scheme_starttls') }}</option>
                        <option value="ssl" @selected(in_array(old('mail_scheme', $config['MAIL_SCHEME']), ['ssl', 'smtps'], true))>{{ __('notification.scheme_ssl') }}</option>
                    </select>
                    @error('mail_scheme') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_username" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_username') }}</label>
                    <input type="text" name="mail_username" id="mail_username"
                        value="{{ old('mail_username', $config['MAIL_USERNAME']) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('mail_username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_password" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_password') }}</label>
                    <input type="password" name="mail_password" id="mail_password"
                        value=""
                        placeholder="{{ $passwordSentinel }}"
                        autocomplete="off"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-sm text-gray-500">{{ __('notification.password_placeholder_help') }}</p>
                    @error('mail_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Sender Settings --}}
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('notification.sender_section') }}</h3>

                <div>
                    <label for="mail_from_address" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_from_address') }}</label>
                    <input type="email" name="mail_from_address" id="mail_from_address"
                        value="{{ old('mail_from_address', $config['MAIL_FROM_ADDRESS']) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('mail_from_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="mail_from_name" class="block text-sm font-medium text-gray-700">{{ __('notification.field_mail_from_name') }}</label>
                    <input type="text" name="mail_from_name" id="mail_from_name"
                        value="{{ old('mail_from_name', $config['MAIL_FROM_NAME']) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('mail_from_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('ui.button_save') }}
                </button>
            </div>
        </form>

        {{-- Test Email --}}
        <div class="bg-white shadow-sm rounded-lg p-6 mt-6">
            <h3 class="text-lg font-medium text-gray-900">{{ __('notification.test_email_section') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('notification.test_email_help_configurable') }}</p>

            <form method="POST" action="{{ route('admin.settings.email.test') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="test_recipient" class="block text-sm font-medium text-gray-700">{{ __('notification.test_email_recipient') }}</label>
                    <input type="email" name="test_recipient" id="test_recipient"
                        value="{{ old('test_recipient', auth()->user()->email) }}"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('notification.test_email_btn') }}
                </button>
            </form>
        </div>
    </div>
</x-admin-layout>
