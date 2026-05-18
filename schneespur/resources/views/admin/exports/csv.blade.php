<x-admin-layout>
    <x-slot name="header">{{ __('export.csv_page_title') }} <x-help-icon topic="exports" /></x-slot>

    <form method="GET" action="{{ route('admin.exports.csv.download') }}" x-data="{ variant: 'all' }" class="mt-4 bg-white shadow-sm rounded-lg p-6 space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Date From --}}
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700">{{ __('export.csv_label_from') }}</label>
                <input type="date" name="from" id="from" value="{{ $defaultFrom }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            {{-- Date To --}}
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700">{{ __('export.csv_label_to') }}</label>
                <input type="date" name="to" id="to" value="{{ $defaultTo }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>

            {{-- Variant --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('export.csv_label_variant') }}</label>
                <div class="mt-2 space-y-2">
                    <label class="inline-flex items-center">
                        <input type="radio" name="variant" value="all" x-model="variant" class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">{{ __('export.csv_variant_all') }}</span>
                    </label>
                    <label class="inline-flex items-center ml-4">
                        <input type="radio" name="variant" value="driver" x-model="variant" class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">{{ __('export.csv_variant_driver') }}</span>
                    </label>
                    <label class="inline-flex items-center ml-4">
                        <input type="radio" name="variant" value="customer" x-model="variant" class="rounded-full border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">{{ __('export.csv_variant_customer') }}</span>
                    </label>
                </div>
            </div>

            {{-- Driver / Customer dropdown --}}
            <div>
                <div x-show="variant === 'driver'" x-cloak>
                    <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('export.csv_label_driver') }}</label>
                    <select name="user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">—</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->displayName() }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="variant === 'customer'" x-cloak>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700">{{ __('export.csv_label_customer') }}</label>
                    <select name="customer_id" id="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">—</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <x-date-range-presets fromId="from" toId="to" />

        {{-- Submit --}}
        <div class="mt-6">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('export.csv_btn_download') }}
            </button>
        </div>
    </form>
</x-admin-layout>
