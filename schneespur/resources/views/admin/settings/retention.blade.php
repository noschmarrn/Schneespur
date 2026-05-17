<x-admin-layout>
    <x-slot name="header">{{ __('settings.retention_title') }} <x-help-icon topic="settings" /></x-slot>

    <div class="max-w-2xl">
        <div class="mb-6 rounded-md bg-amber-50 border border-amber-200 p-4">
            <p class="text-sm text-amber-800">{{ __('settings.retention_legal_notice') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.retention.update') }}" class="space-y-6">
            @csrf

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('settings.retention_description') }}</h3>

                <div>
                    <label for="retention_years" class="block text-sm font-medium text-gray-700">{{ __('settings.retention_years') }}</label>
                    <input type="number" name="retention_years" id="retention_years"
                           value="{{ old('retention_years', $retention_years) }}"
                           class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           min="3" step="1" required>
                    <p class="mt-1 text-sm text-gray-500">{{ __('settings.retention_years_help') }}</p>
                    <p class="mt-1 text-sm text-amber-600">{{ __('settings.retention_years_minimum_warning', ['min' => 3]) }}</p>
                    @error('retention_years')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-start">
                    <div class="flex h-6 items-center">
                        <input type="hidden" name="retention_auto_delete" value="0">
                        <input type="checkbox" name="retention_auto_delete" id="retention_auto_delete" value="1"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               @checked(old('retention_auto_delete', $retention_auto_delete))>
                    </div>
                    <div class="ml-3">
                        <label for="retention_auto_delete" class="text-sm font-medium text-gray-700">{{ __('settings.retention_auto_delete') }}</label>
                        <p class="text-sm text-gray-500">{{ __('settings.retention_auto_delete_help') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('settings.button_save') }}
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
