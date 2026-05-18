<x-guest-layout>
    <div class="mb-4 text-center">
        <h2 class="text-lg font-semibold text-gray-800">{{ __('portal.login_title') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ __('portal.login_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('portal.login') }}">
        @csrf

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('portal.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                          :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Password --}}
        <div class="mt-4">
            <x-input-label for="password" :value="__('portal.password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Remember Me --}}
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                       name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('portal.remember_me') }}</span>
            </label>
        </div>

        <div class="mt-4">
            <x-primary-button class="w-full justify-center">
                {{ __('portal.login_button') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
