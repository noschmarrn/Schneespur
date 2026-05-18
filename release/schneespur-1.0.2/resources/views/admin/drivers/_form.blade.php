<div class="space-y-8">
    {{-- Stammdaten --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('driver.fieldset_master_data') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('driver.field_name')" :required="true" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $driver->name ?? '')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="email" :value="__('driver.field_email')" :required="true" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $driver->email ?? '')" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="password" :value="__('driver.field_password')" :required="! $driver->exists" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $driver->exists" autocomplete="new-password" />
                @if ($driver->exists)
                    <p class="mt-1 text-sm text-gray-500">{{ __('driver.password_hint_edit') }}</p>
                @endif
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Fahrzeug --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('driver.fieldset_vehicle') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="default_vehicle_id" :value="__('driver.field_default_vehicle')" />
                <select id="default_vehicle_id" name="default_vehicle_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('driver.field_default_vehicle_none') }}</option>
                    @foreach ($vehicles ?? [] as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('default_vehicle_id', $driver->default_vehicle_id ?? '') == $vehicle->id)>{{ $vehicle->displayLabel() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-gray-500">{{ __('driver.field_default_vehicle_hint') }}</p>
                <x-input-error :messages="$errors->get('default_vehicle_id')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Kontakt --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('driver.fieldset_contact') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="phone" :value="__('driver.field_phone')" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $driver->phone ?? '')" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="notes" :value="__('driver.field_notes')" />
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $driver->notes ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>
        </div>
    </fieldset>
</div>
