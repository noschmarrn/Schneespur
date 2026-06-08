<x-portal-layout>
    <div class="max-w-2xl">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('portal.profile_title') }}</h1>
        <p class="mt-1 text-sm text-gray-600">{{ __('portal.profile_subtitle') }}</p>

        <form method="POST" action="{{ route('portal.profile.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PATCH')

            {{-- Email --}}
            <div>
                <x-input-label for="email" :value="__('portal.profile_email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $customer->email)" required autocomplete="email" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            {{-- Locale --}}
            <div>
                <x-input-label for="locale" :value="__('portal.profile_locale')" />
                <select id="locale" name="locale" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach(app(\App\Services\Extension\LocaleRegistry::class)->labels() as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $customer->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('locale')" />
            </div>

            <hr class="border-gray-200">

            {{-- Password section --}}
            <div>
                <h2 class="text-lg font-medium text-gray-900">{{ __('portal.profile_section_password') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('portal.profile_section_password_description') }}</p>
            </div>

            <div>
                <x-input-label for="current_password" :value="__('portal.profile_current_password')" />
                <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
            </div>

            <div>
                <x-input-label for="password" :value="__('portal.profile_new_password')" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error class="mt-2" :messages="$errors->get('password')" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('portal.profile_confirm_password')" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('portal.profile_save') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-portal-layout>
