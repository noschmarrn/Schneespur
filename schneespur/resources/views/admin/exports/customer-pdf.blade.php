<x-admin-layout>
    <x-slot name="header">{{ __('export.pdf_page_title') }} <x-help-icon topic="exports" /></x-slot>

    <x-page-header :title="__('export.pdf_page_title')" />

    <form method="POST" action="{{ route('admin.exports.customer-pdf.generate') }}"
          x-data="{
              showWarning: {{ session('warning') ? 'true' : 'false' }},
              confirmed: false,
              submitForm(e) {
                  if (this.showWarning && !this.confirmed) {
                      e.preventDefault();
                      return;
                  }
              }
          }"
          @submit="submitForm($event)"
          class="mt-4 bg-white shadow-sm rounded-lg p-6">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Customer --}}
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-700">{{ __('export.pdf_label_customer') }}</label>
                <select name="customer_id" id="customer_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">—</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $selectedCustomer) == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date From --}}
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700">{{ __('export.pdf_label_from') }}</label>
                <input type="date" name="from" id="from" value="{{ old('from', $defaultFrom) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('from')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date To --}}
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700">{{ __('export.pdf_label_to') }}</label>
                <input type="date" name="to" id="to" value="{{ old('to', $defaultTo) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('to')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Include Active --}}
            <div class="flex items-end">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="include_active" value="1" {{ old('include_active') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">{{ __('export.pdf_label_include_active') }}</span>
                </label>
            </div>
        </div>

        {{-- Warning dialog for >50 jobs --}}
        <div x-show="showWarning && !confirmed" x-cloak class="mt-4 rounded-md bg-yellow-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">{{ session('warning') }}</p>
                    <div class="mt-3">
                        <button type="submit" @click="confirmed = true" class="inline-flex items-center px-3 py-1.5 border border-yellow-600 text-xs font-medium rounded text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                            {{ __('export.pdf_btn_generate') }}
                        </button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="confirmed" x-bind:value="confirmed ? '1' : '0'">
        </div>

        {{-- Submit --}}
        <div class="mt-6" x-show="!showWarning || confirmed">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('export.pdf_btn_generate') }}
            </button>
        </div>
    </form>
</x-admin-layout>
