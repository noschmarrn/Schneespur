@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_8') }}</h2>

    @if($errors->has('mail'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-700">{{ $errors->first('mail') }}</p>
        </div>
    @endif

    @if(session('flash_test_mail_success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm text-green-700">{{ session('flash_test_mail_success') }}</p>
        </div>
    @endif

    <p class="text-gray-600 mb-4">{{ __('install.mail_heading') }}</p>

    <form method="POST" action="{{ route('install.mail.send') }}" x-data="{
        encryption: '{{ old('mail_encryption', 'tls') }}',
        port: '{{ old('mail_port', '587') }}',
        portMap: { tls: '587', ssl: '465', 'null': '25' },
        updatePort() { this.port = this.portMap[this.encryption] || this.port; }
    }">
        @csrf
        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="mail_host" :value="__('install.mail_host_label')" />
                    <x-text-input id="mail_host" name="mail_host" type="text" class="mt-1 block w-full" :value="old('mail_host')" required />
                    <x-input-error :messages="$errors->get('mail_host')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="mail_port" :value="__('install.mail_port_label')" />
                    <x-text-input id="mail_port" name="mail_port" type="number" class="mt-1 block w-full" x-model="port" required />
                    <x-input-error :messages="$errors->get('mail_port')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="mail_encryption" :value="__('install.mail_encryption_label')" />
                <select name="mail_encryption" id="mail_encryption" x-model="encryption" x-on:change="updatePort()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="tls">{{ __('install.mail_scheme_starttls') }}</option>
                    <option value="ssl">{{ __('install.mail_scheme_ssl') }}</option>
                    <option value="null">{{ __('install.mail_scheme_none') }}</option>
                </select>
                <x-input-error :messages="$errors->get('mail_encryption')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="mail_username" :value="__('install.mail_user_label')" />
                    <x-text-input id="mail_username" name="mail_username" type="text" class="mt-1 block w-full" :value="old('mail_username')" autocomplete="username" />
                    <x-input-error :messages="$errors->get('mail_username')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="mail_password" :value="__('install.mail_pass_label')" />
                    <x-text-input id="mail_password" name="mail_password" type="password" class="mt-1 block w-full" :value="old('mail_password')" autocomplete="off" />
                    <x-input-error :messages="$errors->get('mail_password')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="mail_from_address" :value="__('install.mail_from_label')" />
                    <x-text-input id="mail_from_address" name="mail_from_address" type="email" class="mt-1 block w-full" :value="old('mail_from_address')" required autocomplete="email" />
                    <x-input-error :messages="$errors->get('mail_from_address')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="mail_from_name" :value="__('install.mail_from_name_label')" />
                    <x-text-input id="mail_from_name" name="mail_from_name" type="text" class="mt-1 block w-full" :value="old('mail_from_name', brand())" required />
                    <x-input-error :messages="$errors->get('mail_from_name')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="test_recipient" :value="__('install.mail_test_recipient_label')" />
                <x-text-input id="test_recipient" name="test_recipient" type="email" class="mt-1 block w-full" :value="old('test_recipient')" required />
                <x-input-error :messages="$errors->get('test_recipient')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <a href="{{ route('install.admin') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <x-primary-button>
                {{ __('install.mail_submit_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('install.mail.skip') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-gray-500 hover:text-gray-700 text-sm underline">
            {{ __('install.mail_skip_btn') }}
        </button>
    </form>
@endsection
