<x-admin-layout>
    <x-slot name="header">{{ __('job.manual_create_title') }} <x-help-icon topic="jobs" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_jobs'), 'url' => route('admin.jobs.index')],
        ['label' => __('job.manual_create_title')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('job.manual_create_title')" />

        @if (session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        @php
            $allObjects = $customers->flatMap(fn ($c) => $c->objects->map(fn ($o) => [
                'id' => $o->id, 'customer_id' => $c->id, 'name' => $o->name, 'street' => $o->street, 'city' => $o->city,
            ]));
        @endphp
        <form method="POST" action="{{ route('admin.jobs.manual.store') }}" class="mt-6"
              x-data="{
                  selectedCustomerId: {{ old('customer_id') ? (int) old('customer_id') : 'null' }},
                  selectedObjectId: {{ old('customer_object_id') ? (int) old('customer_object_id') : 'null' }},
                  allObjects: {{ json_encode($allObjects) }},
                  get objects() { return this.allObjects.filter(o => o.customer_id === this.selectedCustomerId); },
                  onCustomerChange() {
                      const objs = this.objects;
                      this.selectedObjectId = objs.length === 1 ? objs[0].id : null;
                  }
              }">
            @csrf

            <div class="space-y-8">
                <fieldset>
                    <legend class="text-base font-semibold text-gray-900">{{ __('job.manual_create_title') }}</legend>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Driver --}}
                        <div>
                            <x-input-label for="user_id" :value="__('job.field_driver')" :required="true" />
                            <select id="user_id" name="user_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">— {{ __('job.field_driver') }} —</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}" @selected(old('user_id') == $driver->id)>{{ $driver->displayName() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                        </div>

                        {{-- Customer --}}
                        <div>
                            <x-input-label for="customer_id" :value="__('job.field_customer')" :required="true" />
                            <select id="customer_id" x-model.number="selectedCustomerId" @change="onCustomerChange()" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">— {{ __('job.field_customer') }} —</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Object --}}
                        <div x-show="selectedCustomerId && objects.length > 1" x-transition class="sm:col-span-2">
                            <x-input-label for="customer_object_id" :value="__('job.field_object')" :required="true" />
                            <select id="customer_object_id" name="customer_object_id" x-model.number="selectedObjectId" x-bind:required="objects.length > 1" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">— {{ __('job.field_object') }} —</option>
                                <template x-for="obj in objects" :key="obj.id">
                                    <option :value="obj.id" x-text="obj.name + (obj.street ? ' (' + obj.street + ')' : '')"></option>
                                </template>
                            </select>
                            <x-input-error :messages="$errors->get('customer_object_id')" class="mt-2" />
                        </div>

                        {{-- Hidden object_id for single-object customers --}}
                        <template x-if="objects.length === 1">
                            <input type="hidden" name="customer_object_id" :value="selectedObjectId">
                        </template>

                        {{-- Customer has no objects — block submit with a visible reason --}}
                        <template x-if="selectedCustomerId && objects.length === 0">
                            <div class="sm:col-span-2 rounded-md bg-amber-50 border border-amber-200 p-3">
                                <p class="text-sm text-amber-800">{{ __('job.manual_no_objects') }}</p>
                            </div>
                        </template>

                        {{-- Job type --}}
                        <div class="sm:col-span-2">
                            <x-input-label for="type" :value="__('job.field_type')" :required="true" />
                            <select id="type" name="type" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">— {{ __('job.field_type') }} —</option>
                                @foreach ($jobTypes as $jobType)
                                    <option value="{{ $jobType->value }}" @selected(old('type') === $jobType->value)>{{ $jobType->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        {{-- Started at --}}
                        <div>
                            <x-input-label for="started_at" :value="__('job.field_started_at')" :required="true" />
                            <x-text-input id="started_at" name="started_at" type="datetime-local" class="mt-1 block w-full" :value="old('started_at')" required />
                            <x-input-error :messages="$errors->get('started_at')" class="mt-2" />
                        </div>

                        {{-- Ended at --}}
                        <div>
                            <x-input-label for="ended_at" :value="__('job.field_ended_at')" :required="true" />
                            <x-text-input id="ended_at" name="ended_at" type="datetime-local" class="mt-1 block w-full" :value="old('ended_at')" required />
                            <x-input-error :messages="$errors->get('ended_at')" class="mt-2" />
                        </div>

                        {{-- Vehicle --}}
                        <div class="sm:col-span-2">
                            <x-input-label for="vehicle_id" :value="__('job.field_vehicle')" />
                            <select id="vehicle_id" name="vehicle_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">— {{ __('driver.dash_vehicle_none') }} —</option>
                                @foreach ($vehicles ?? [] as $vehicle)
                                    <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->displayLabel() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('vehicle_id')" class="mt-2" />
                        </div>

                        {{-- Notes --}}
                        <div class="sm:col-span-2">
                            <x-input-label for="notes" :value="__('job.field_notes')" />
                            <textarea id="notes" name="notes" rows="3" maxlength="1000" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button x-bind:disabled="selectedCustomerId && objects.length === 0">{{ __('ui.button_create') }}</x-primary-button>
                <a href="{{ route('admin.jobs.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>
</x-admin-layout>
