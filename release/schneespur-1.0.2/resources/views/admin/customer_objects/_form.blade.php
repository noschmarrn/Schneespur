<div class="space-y-8">
    {{-- Stammdaten --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('customer_object.fieldset_master_data') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('customer_object.field_name')" :required="true" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $object->name ?? '')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="street" :value="__('customer_object.field_street')" />
                <x-text-input id="street" name="street" type="text" class="mt-1 block w-full" :value="old('street', $object->street ?? '')" />
                <x-input-error :messages="$errors->get('street')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="zip" :value="__('customer_object.field_zip')" />
                <x-text-input id="zip" name="zip" type="text" class="mt-1 block w-full" :value="old('zip', $object->zip ?? '')" />
                <x-input-error :messages="$errors->get('zip')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="city" :value="__('customer_object.field_city')" />
                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $object->city ?? '')" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Kontakt --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('customer_object.fieldset_contact') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="contact_name" :value="__('customer_object.field_contact_name')" />
                <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" :value="old('contact_name', $object->contact_name ?? '')" />
                <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="contact_email" :value="__('customer_object.field_contact_email')" />
                <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email', $object->contact_email ?? '')" />
                <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="contact_phone" :value="__('customer_object.field_contact_phone')" />
                <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full" :value="old('contact_phone', $object->contact_phone ?? '')" />
                <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Preise --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('customer_object.fieldset_pricing') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="price_amount" :value="__('customer_object.field_price_amount')" />
                <div class="relative mt-1">
                    <x-text-input id="price_amount" name="price_amount" type="text" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" class="block w-full pr-10" :value="old('price_amount', isset($object) && $object->price_amount_cents ? number_format($object->price_amount_cents / 100, 2, ',', '') : '')" />
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 text-sm">€</span>
                </div>
                <x-input-error :messages="$errors->get('price_amount')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="price_unit" :value="__('customer_object.field_price_unit')" />
                <select id="price_unit" name="price_unit" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">{{ __('customer_object.price_unit_placeholder') }}</option>
                    <option value="per_job" @selected(old('price_unit', $object->price_unit ?? '') === 'per_job')>{{ __('customer_object.price_unit_per_job') }}</option>
                    <option value="monthly" @selected(old('price_unit', $object->price_unit ?? '') === 'monthly')>{{ __('customer_object.price_unit_monthly') }}</option>
                    <option value="seasonal" @selected(old('price_unit', $object->price_unit ?? '') === 'seasonal')>{{ __('customer_object.price_unit_seasonal') }}</option>
                </select>
                <x-input-error :messages="$errors->get('price_unit')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Einsatz-Parameter --}}
    <fieldset>
        <legend class="text-base font-semibold text-gray-900">{{ __('customer_object.fieldset_operations') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="plow_threshold_cm" :value="__('customer_object.field_plow_threshold')" />
                <x-text-input id="plow_threshold_cm" name="plow_threshold_cm" type="number" min="0" max="255" step="1" class="mt-1 block w-full" :value="old('plow_threshold_cm', $object->plow_threshold_cm ?? '')" />
                <x-input-error :messages="$errors->get('plow_threshold_cm')" class="mt-2" />
            </div>

            <div class="flex items-center pt-6">
                <input id="salt_enabled" name="salt_enabled" type="hidden" value="0">
                <input id="salt_enabled_checkbox" name="salt_enabled" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('salt_enabled', $object->salt_enabled ?? false))>
                <x-input-label for="salt_enabled_checkbox" :value="__('customer_object.field_salt_enabled')" class="ml-2" />
                <x-input-error :messages="$errors->get('salt_enabled')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="site_notes" :value="__('customer_object.field_site_notes')" />
                <textarea id="site_notes" name="site_notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('site_notes', $object->site_notes ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('site_notes')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="access_notes" :value="__('customer_object.field_access_notes')" />
                <textarea id="access_notes" name="access_notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('access_notes', $object->access_notes ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('access_notes')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- Standort --}}
    <fieldset x-data="objectGeocoder()">
        <legend class="text-base font-semibold text-gray-900">{{ __('customer_object.fieldset_location') }}</legend>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="lat" :value="__('customer_object.field_lat')" />
                <x-text-input id="lat" name="lat" type="number" step="0.0000001" min="-90" max="90" class="mt-1 block w-full" :value="old('lat', $object->lat ?? '')" x-model="lat" />
                <x-input-error :messages="$errors->get('lat')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="lon" :value="__('customer_object.field_lon')" />
                <x-text-input id="lon" name="lon" type="number" step="0.0000001" min="-180" max="180" class="mt-1 block w-full" :value="old('lon', $object->lon ?? '')" x-model="lon" />
                <x-input-error :messages="$errors->get('lon')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <button type="button" x-on:click="lookup()" :disabled="loading" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                    <svg x-show="loading" x-cloak class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    {{ __('customer_object.btn_geocode') }}
                </button>
                <p x-show="message" x-text="message" x-transition class="mt-2 text-sm" :class="success ? 'text-green-600' : 'text-red-600'"></p>
            </div>
        </div>
    </fieldset>

    <script>
        function objectGeocoder() {
            return {
                lat: document.getElementById('lat')?.value || '',
                lon: document.getElementById('lon')?.value || '',
                loading: false,
                message: '',
                success: false,
                async lookup() {
                    const street = document.getElementById('street')?.value?.trim();
                    const zip = document.getElementById('zip')?.value?.trim();
                    const city = document.getElementById('city')?.value?.trim();
                    if (!street || !zip || !city) {
                        this.success = false;
                        this.message = @json(__('customer_object.geocode_address_required'));
                        return;
                    }
                    this.loading = true;
                    this.message = '';
                    try {
                        const resp = await fetch('{{ route('admin.customers.geocode') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ street, zip, city }),
                        });
                        const data = await resp.json();
                        if (resp.ok) {
                            this.lat = data.lat;
                            this.lon = data.lon;
                            this.success = true;
                            this.message = @json(__('customer_object.geocode_success'));
                        } else {
                            this.success = false;
                            this.message = data.error || @json(__('customer_object.geocode_failed'));
                        }
                    } catch (e) {
                        this.success = false;
                        this.message = @json(__('customer_object.geocode_failed'));
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>

    {{-- Benachrichtigung --}}
    <fieldset x-data="{ autoNotify: {{ old('auto_notify_email', $object->auto_notify_email ?? false) ? 'true' : 'false' }} }">
        <legend class="text-base font-semibold text-gray-900">{{ __('customer_object.fieldset_notification') }}</legend>
        <div class="mt-4 space-y-4">
            <div class="flex items-center">
                <input id="auto_notify_email" name="auto_notify_email" type="hidden" value="0">
                <input id="auto_notify_email_checkbox" name="auto_notify_email" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" x-model="autoNotify" @checked(old('auto_notify_email', $object->auto_notify_email ?? false))>
                <x-input-label for="auto_notify_email_checkbox" :value="__('customer_object.field_auto_notify')" class="ml-2" />
                <x-input-error :messages="$errors->get('auto_notify_email')" class="mt-2" />
            </div>

            <div x-show="autoNotify" x-transition>
                <x-input-label for="notification_email" :value="__('customer_object.field_notification_email')" />
                <x-text-input id="notification_email" name="notification_email" type="email" class="mt-1 block w-full" :value="old('notification_email', $object->notification_email ?? '')" />
                <x-input-error :messages="$errors->get('notification_email')" class="mt-2" />
            </div>

            <div x-show="autoNotify" x-transition>
                <x-input-label for="notify_recipients" :value="__('customer_object.field_notify_recipients')" />
                <x-text-input id="notify_recipients" name="notify_recipients" type="text" class="mt-1 block w-full" :value="old('notify_recipients', $object->notify_recipients ?? '')" />
                <x-input-error :messages="$errors->get('notify_recipients')" class="mt-2" />
            </div>
        </div>
    </fieldset>
</div>
