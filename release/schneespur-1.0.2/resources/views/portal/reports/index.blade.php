<x-portal-layout>
    <h1 class="text-2xl font-bold text-gray-900">{{ __('portal.reports_title') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('portal.reports_description') }}</p>

    <form method="POST" action="{{ route('portal.reports.generate') }}" class="mt-6 bg-white shadow-sm rounded-lg p-6">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- Date From --}}
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700">{{ __('portal.reports_date_from') }}</label>
                <input type="date" name="from" id="from" value="{{ old('from', $defaultFrom) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('from')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Date To --}}
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700">{{ __('portal.reports_date_to') }}</label>
                <input type="date" name="to" id="to" value="{{ old('to', $defaultTo) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('to')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Object Filter --}}
            <div>
                <label for="customer_object_id" class="block text-sm font-medium text-gray-700">{{ __('portal.reports_object_filter') }}</label>
                <select name="customer_object_id" id="customer_object_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('portal.reports_all_objects') }}</option>
                    @foreach ($objects as $object)
                        <option value="{{ $object->id }}" @selected(old('customer_object_id') == $object->id)>{{ $object->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-6">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('portal.reports_generate') }}
            </button>
        </div>
    </form>
</x-portal-layout>
