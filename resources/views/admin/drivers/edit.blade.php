<x-admin-layout>
    <x-slot name="header">{{ __('driver.page_edit') }} <x-help-icon topic="drivers" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_drivers'), 'url' => route('admin.drivers.index')],
        ['label' => __('driver.page_edit')],
    ]" />

    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <x-page-header :title="__('driver.page_edit')" />

        <form method="POST" action="{{ route('admin.drivers.update', $driver) }}" class="mt-6">
            @csrf
            @method('PUT')

            @include('admin.drivers._form', ['driver' => $driver])

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('ui.button_save') }}</x-primary-button>
                <a href="{{ route('admin.drivers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.button_cancel') }}</a>
            </div>
        </form>
    </div>

    {{-- OwnTracks Credentials Section --}}
    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('driver.owntracks_section_title') }}</h2>

        @if ($driver->owntracks_username)
            <div class="mt-4 space-y-3">
                <div>
                    <span class="text-sm font-medium text-gray-500">{{ __('driver.field_owntracks_username') }}</span>
                    <p class="mt-1 text-sm text-gray-900 font-mono">{{ $driver->owntracks_username }}</p>
                </div>

                <div class="pt-2">
                    <button x-data x-on:click="$dispatch('open-modal', 'rotate-credentials-{{ $driver->id }}')" type="button" class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-500 focus:bg-amber-500 active:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('driver.btn_rotate_password') }}
                    </button>
                </div>
            </div>

            <x-confirm-dialog
                :name="'rotate-credentials-' . $driver->id"
                :title="__('driver.modal_rotate_title')"
                :message="__('driver.modal_rotate_body1', ['name' => e($driver->name)]) . ' ' . __('driver.modal_rotate_body2')"
            >
                <x-slot name="action">
                    <form method="POST" action="{{ route('admin.drivers.rotate-credentials', $driver) }}">
                        @csrf
                        <x-danger-button>{{ __('driver.modal_rotate_submit') }}</x-danger-button>
                    </form>
                </x-slot>
            </x-confirm-dialog>
        @else
            <p class="mt-4 text-sm text-gray-500">{{ __('driver.owntracks_no_credentials') }}</p>
        @endif
    </div>

    {{-- Data Export Section (DSGVO Art. 15/20) --}}
    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('driver.export_section_heading') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('driver.export_section_body') }}</p>
        <div class="mt-4">
            <a href="{{ route('admin.drivers.export', $driver) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('driver.btn_export') }}
            </a>
        </div>
    </div>

    {{-- Anonymization Section (DSGVO Art. 17) --}}
    <div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 text-red-700">{{ __('driver.modal_anonymize_title') }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ __('driver.modal_anonymize_body2') }}</p>

        <div class="mt-4">
            <button x-data x-on:click="$dispatch('open-modal', 'anonymize-driver-{{ $driver->id }}')" type="button" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('driver.btn_anonymize') }}
            </button>
        </div>

        <x-modal :name="'anonymize-driver-' . $driver->id" maxWidth="md" focusable>
            <div class="p-6" x-data="{ confirmName: '', reason: '' }">
                <h2 class="text-lg font-medium text-gray-900">{{ __('driver.modal_anonymize_title') }}</h2>

                <p class="mt-2 text-sm text-gray-600">{!! __('driver.modal_anonymize_body1', ['name' => e($driver->name)]) !!}</p>
                <p class="mt-2 text-sm text-gray-600">{{ __('driver.modal_anonymize_body2') }}</p>

                <form method="POST" action="{{ route('admin.drivers.anonymize', $driver) }}">
                    @csrf

                    <div class="mt-4">
                        <x-input-label for="confirmation_name" :value="__('driver.modal_anonymize_confirm_label')" />
                        <x-text-input id="confirmation_name" class="mt-1 block w-full" type="text" x-model="confirmName" :placeholder="__('driver.modal_anonymize_confirm_placeholder')" autocomplete="off" />
                        <input type="hidden" name="confirmation_name" x-bind:value="confirmName" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="reason" :value="__('driver.modal_anonymize_reason_label')" />
                        <textarea id="reason" name="reason" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" rows="3" x-model="reason" placeholder="{{ __('driver.modal_anonymize_reason_placeholder') }}" required></textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('driver.modal_anonymize_reason_helper') }}</p>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <x-secondary-button x-on:click="$dispatch('close-modal', 'anonymize-driver-{{ $driver->id }}')">
                            {{ __('ui.button_cancel') }}
                        </x-secondary-button>

                        <x-danger-button type="submit" x-bind:disabled="confirmName !== '{{ $driver->name }}'">
                            {{ __('driver.modal_anonymize_submit') }}
                        </x-danger-button>
                    </div>
                </form>
            </div>
        </x-modal>
    </div>
</x-admin-layout>
