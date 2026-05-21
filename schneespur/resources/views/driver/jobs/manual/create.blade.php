<x-driver-layout>
    <div class="px-4 py-6 space-y-6">
        <h2 class="text-xl font-semibold text-gray-100">{{ __('job.manual_create_title') }}</h2>

        @if (session('success'))
            <div class="rounded-lg p-4 bg-green-900/50 border border-green-700">
                <p class="text-sm text-green-300">{{ session('success') }}</p>
            </div>
        @endif

        @php
            $allObjects = $customers->flatMap(fn ($c) => $c->objects->map(fn ($o) => [
                'id' => $o->id, 'customer_id' => $c->id, 'name' => $o->name, 'street' => $o->street, 'city' => $o->city,
            ]));
        @endphp
        <form method="POST" action="{{ route('driver.job.manual.store') }}" class="space-y-5"
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

            {{-- Customer --}}
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-300">{{ __('job.field_customer') }} <span class="text-red-400">*</span></label>
                <select id="customer_id" x-model.number="selectedCustomerId" @change="onCustomerChange()" class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— {{ __('job.field_customer') }} —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Object --}}
            <div x-show="selectedCustomerId && objects.length > 1" x-transition>
                <label for="customer_object_id" class="block text-sm font-medium text-gray-300">{{ __('job.field_object') }} <span class="text-red-400">*</span></label>
                <select id="customer_object_id" name="customer_object_id" x-model.number="selectedObjectId" required class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
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

            {{-- Job type --}}
            <div>
                <label for="type" class="block text-sm font-medium text-gray-300">{{ __('job.field_type') }} <span class="text-red-400">*</span></label>
                <select id="type" name="type" required class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— {{ __('job.field_type') }} —</option>
                    @foreach ($jobTypes as $jobType)
                        <option value="{{ $jobType->value }}" @selected(old('type') === $jobType->value)>{{ $jobType->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>

            {{-- Started at --}}
            <div>
                <label for="started_at" class="block text-sm font-medium text-gray-300">{{ __('job.field_started_at') }} <span class="text-red-400">*</span></label>
                <input id="started_at" name="started_at" type="datetime-local" value="{{ old('started_at') }}" required class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                <x-input-error :messages="$errors->get('started_at')" class="mt-2" />
            </div>

            {{-- Ended at --}}
            <div>
                <label for="ended_at" class="block text-sm font-medium text-gray-300">{{ __('job.field_ended_at') }} <span class="text-red-400">*</span></label>
                <input id="ended_at" name="ended_at" type="datetime-local" value="{{ old('ended_at') }}" required class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                <x-input-error :messages="$errors->get('ended_at')" class="mt-2" />
            </div>

            {{-- Vehicle --}}
            <div>
                <label for="vehicle_id" class="block text-sm font-medium text-gray-300">{{ __('job.field_vehicle') }}</label>
                <select id="vehicle_id" name="vehicle_id" class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— {{ __('driver.dash_vehicle_none') }} —</option>
                    @foreach ($vehicles ?? [] as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->displayLabel() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('vehicle_id')" class="mt-2" />
            </div>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-300">{{ __('job.field_notes') }}</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000" class="mt-1 block w-full rounded-lg bg-gray-800 border-gray-600 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <button type="submit" class="w-full flex justify-center py-3 px-4 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900 min-h-[48px]">
                {{ __('ui.button_create') }}
            </button>
        </form>
    </div>
</x-driver-layout>
