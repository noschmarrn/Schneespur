<div class="space-y-8">
    {{-- Stammdaten --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('user.fieldset_master_data') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('user.field_name')" :required="true" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name ?? '')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="email" :value="__('user.field_email')" :required="true" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email ?? '')" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="password" :value="__('user.field_password')" :required="! $user->exists" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $user->exists" autocomplete="new-password" />
                @if ($user->exists)
                    <p class="mt-1 text-sm text-gray-500">{{ __('user.password_hint_edit') }}</p>
                @endif
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Rollen --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('user.fieldset_roles') }}</legend>
        <div class="mt-4 space-y-3">
            @foreach ($roles as $role)
                <div class="flex items-center">
                    <input
                        id="role_{{ $role->id }}"
                        name="roles[]"
                        type="checkbox"
                        value="{{ $role->id }}"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        @checked(in_array($role->id, old('roles', $user->roles->pluck('id')->all())))
                    >
                    <label for="role_{{ $role->id }}" class="ml-2 text-sm text-gray-700">
                        {{ $role->name }}
                        @if ($role->is_locked)
                            <span class="inline-flex items-center bg-gray-100 text-gray-500 rounded-full px-2 py-0.5 text-xs ml-1">{{ __('user.role_locked_badge') }}</span>
                        @endif
                    </label>
                </div>
            @endforeach
            <x-input-error :messages="$errors->get('roles')" class="mt-2" />
        </div>
    </fieldset>
</div>
