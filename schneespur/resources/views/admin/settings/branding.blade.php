@use('Illuminate\Support\Facades\Storage')
<x-admin-layout>
    <x-slot name="header">{{ __('ui.branding_title') }} <x-help-icon topic="settings" /></x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Logo --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    {{ __('ui.branding_logo') }}
                </label>

                @if ($logoPath)
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-2">{{ __('ui.branding_logo_current') }}</p>
                        <img src="{{ Storage::disk('public')->url($logoPath) }}"
                             alt="{{ __('ui.branding_logo') }}"
                             class="max-h-24 border border-gray-200 rounded p-2 bg-white">
                    </div>
                @endif

                <input type="file"
                       name="company_logo"
                       id="company_logo"
                       accept="image/png,image/jpeg,image/svg+xml"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-sm text-gray-500">{{ __('ui.branding_logo_help') }}</p>
                @error('company_logo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('ui.button_save') }}
                </button>
            </div>
        </form>

        @if ($logoPath)
            <form method="POST" action="{{ route('admin.settings.branding.delete-logo') }}" class="mt-4"
                  x-data
                  x-on:submit.prevent="if (confirm('{{ __('ui.branding_logo_delete_confirm') }}')) $el.submit()">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-md font-semibold text-xs text-red-700 uppercase tracking-widest hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('ui.branding_logo_delete') }}
                </button>
            </form>
        @endif
    </div>
</x-admin-layout>
