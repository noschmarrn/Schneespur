<div class="space-y-8">
    <fieldset>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('vehicle.field_name')" :required="true" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $vehicle->name ?? '')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="license_plate" :value="__('vehicle.field_license_plate')" />
                <x-text-input id="license_plate" name="license_plate" type="text" class="mt-1 block w-full" :value="old('license_plate', $vehicle->license_plate ?? '')" />
                <x-input-error :messages="$errors->get('license_plate')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="owntracks_device_id" :value="__('vehicle.field_owntracks_device_id')" />
                <x-text-input id="owntracks_device_id" name="owntracks_device_id" type="text" class="mt-1 block w-full" :value="old('owntracks_device_id', $vehicle->owntracks_device_id ?? '')" />
                <x-input-error :messages="$errors->get('owntracks_device_id')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="notes" :value="__('vehicle.field_notes')" />
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $vehicle->notes ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>
        </div>
    </fieldset>
</div>
