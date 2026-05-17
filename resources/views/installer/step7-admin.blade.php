@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_7') }}</h2>

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            @foreach($errors->all() as $error)
                <p class="text-sm text-red-700">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('install.admin.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <x-input-label for="name" :value="__('install.admin_name_label')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" :value="__('install.admin_email_label')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('install.admin_pass_label')" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('install.admin_pass_confirm_label')" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <a href="{{ route('install.storage') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <x-primary-button>
                {{ __('install.admin_submit_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>
@endsection
