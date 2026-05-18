<div class="space-y-8">
    {{-- Stammdaten --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('customer.fieldset_master_data') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('customer.field_name')" :required="true" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $customer->name ?? '')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Kontakt --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('customer.fieldset_contact') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="contact_name" :value="__('customer.field_contact_name')" />
                <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" :value="old('contact_name', $customer->contact_name ?? '')" />
                <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" :value="__('customer.field_email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $customer->email ?? '')" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" :value="__('customer.field_phone')" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $customer->phone ?? '')" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Benachrichtigung --}}
    <fieldset x-data="{ autoNotify: {{ old('auto_notify_email', $customer->auto_notify_email ?? false) ? 'true' : 'false' }} }">
        <legend class="text-base font-semibold text-gray-900">{{ __('customer.fieldset_notification') }}</legend>
        <div class="mt-4 space-y-4">
            <div class="flex items-center">
                <input id="auto_notify_email" name="auto_notify_email" type="hidden" value="0">
                <input id="auto_notify_email_checkbox" name="auto_notify_email" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" x-model="autoNotify" @checked(old('auto_notify_email', $customer->auto_notify_email ?? false))>
                <x-input-label for="auto_notify_email_checkbox" :value="__('customer.field_auto_notify')" class="ml-2" />
                <x-input-error :messages="$errors->get('auto_notify_email')" class="mt-2" />
            </div>

            <div x-show="autoNotify" x-transition>
                <x-input-label for="notification_email" :value="__('customer.field_notification_email')" />
                <x-text-input id="notification_email" name="notification_email" type="email" class="mt-1 block w-full" :value="old('notification_email', $customer->notification_email ?? '')" />
                <x-input-error :messages="$errors->get('notification_email')" class="mt-2" />
            </div>

            <div x-show="autoNotify" x-transition>
                <x-input-label for="locale" :value="__('customer.field_locale')" />
                <select id="locale" name="locale" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="de" @selected(old('locale', $customer->locale ?? 'de') === 'de')>{{ __('customer.locale_de') }}</option>
                    <option value="en" @selected(old('locale', $customer->locale ?? 'de') === 'en')>{{ __('customer.locale_en') }}</option>
                </select>
                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
            </div>
        </div>
    </fieldset>
</div>
