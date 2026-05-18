@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_3') }}</h2>

    @if($errors->has('db_connection'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-700">{{ $errors->first('db_connection') }}</p>
        </div>
    @endif

    @if(session('env_content') || ($env_content ?? null))
        @include('installer._env-fallback', ['envContent' => session('env_content') ?: $env_content])
    @endif

    <form method="POST" action="{{ route('install.database.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <x-input-label for="db_host" :value="__('install.db_host_label')" />
                <x-text-input id="db_host" name="db_host" type="text" class="mt-1 block w-full" :value="old('db_host', '127.0.0.1')" required />
                <x-input-error :messages="$errors->get('db_host')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_port" :value="__('install.db_port_label')" />
                <x-text-input id="db_port" name="db_port" type="number" class="mt-1 block w-full" :value="old('db_port', '3306')" required />
                <x-input-error :messages="$errors->get('db_port')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_database" :value="__('install.db_name_label')" />
                <x-text-input id="db_database" name="db_database" type="text" class="mt-1 block w-full" :value="old('db_database')" required />
                <x-input-error :messages="$errors->get('db_database')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_username" :value="__('install.db_user_label')" />
                <x-text-input id="db_username" name="db_username" type="text" class="mt-1 block w-full" :value="old('db_username')" required autocomplete="off" />
                <x-input-error :messages="$errors->get('db_username')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="db_password" :value="__('install.db_pass_label')" />
                <x-text-input id="db_password" name="db_password" type="password" class="mt-1 block w-full" :value="old('db_password')" autocomplete="off" />
                <x-input-error :messages="$errors->get('db_password')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <a href="{{ route('install.preflight') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <x-primary-button>
                {{ __('install.db_submit_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>
@endsection
