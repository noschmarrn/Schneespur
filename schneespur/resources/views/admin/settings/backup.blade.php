<x-admin-layout>
    <x-slot name="header">{{ __('backup.settings_title') }}</x-slot>

    <div class="max-w-2xl">

        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.backup.update') }}" class="space-y-6">
            @csrf

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('backup.settings_description') }}</h3>

                <div>
                    <label for="backup_target" class="block text-sm font-medium text-gray-700">{{ __('backup.settings_target') }}</label>
                    <select name="backup_target" id="backup_target"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @foreach($targets as $slug => $info)
                            <option value="{{ $slug }}" @selected(old('backup_target', $activeTarget) === $slug)>
                                {{ $info['label'] }}
                                @unless($info['configured']) ({{ __('Not configured') }}) @endunless
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-gray-500">{{ __('backup.settings_target_help') }}</p>
                    @error('backup_target')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
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
