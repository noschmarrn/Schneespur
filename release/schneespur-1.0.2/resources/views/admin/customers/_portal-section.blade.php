<div class="mt-6 bg-white overflow-hidden shadow-sm rounded-lg p-6">
    <h3 class="text-lg font-medium text-gray-900">{{ __('customer.fieldset_portal') }}</h3>

    {{-- Status + Setup/Reset --}}
    <div class="mt-4 flex items-center gap-4">
        <span class="text-sm font-medium text-gray-700">{{ __('customer.portal_status_label') }}:</span>

        @if ($customer->password)
            @if ($customer->portal_enabled)
                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                    {{ __('customer.portal_status_active') }}
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20">
                    {{ __('customer.portal_status_inactive') }}
                </span>
            @endif
        @else
            <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-500/10">
                {{ __('customer.portal_status_not_configured') }}
            </span>
        @endif
    </div>

    @if (! $customer->email)
        <p class="mt-3 text-sm text-amber-600">{{ __('customer.portal_no_email') }}</p>
    @else
        <div class="mt-4">
            @if ($customer->password)
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'portal-reset')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                    {{ __('customer.portal_btn_reset') }}
                </button>

                <x-confirm-dialog
                    name="portal-reset"
                    :title="__('customer.portal_btn_reset')"
                    :message="__('customer.portal_confirm_reset')"
                >
                    <x-slot name="action">
                        <form method="POST" action="{{ route('admin.customers.portal-access', $customer) }}">
                            @csrf
                            <x-primary-button>{{ __('customer.portal_btn_reset') }}</x-primary-button>
                        </form>
                    </x-slot>
                </x-confirm-dialog>
            @else
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'portal-setup')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700">
                    {{ __('customer.portal_btn_setup') }}
                </button>

                <x-confirm-dialog
                    name="portal-setup"
                    :title="__('customer.portal_btn_setup')"
                    :message="__('customer.portal_confirm_setup')"
                >
                    <x-slot name="action">
                        <form method="POST" action="{{ route('admin.customers.portal-access', $customer) }}">
                            @csrf
                            <x-primary-button>{{ __('customer.portal_btn_setup') }}</x-primary-button>
                        </form>
                    </x-slot>
                </x-confirm-dialog>
            @endif
        </div>
    @endif

    {{-- Visibility Settings --}}
    @if ($customer->password)
        <form method="POST" action="{{ route('admin.customers.portal-settings', $customer) }}" class="mt-6 border-t pt-6">
            @csrf
            @method('PUT')

            <h4 class="text-sm font-medium text-gray-700">{{ __('customer.portal_visibility_heading') }}</h4>

            <div class="mt-3 space-y-3">
                <div class="flex items-center">
                    <input type="hidden" name="portal_enabled" value="0">
                    <input id="portal_enabled" name="portal_enabled" type="checkbox" value="1"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                           @checked(old('portal_enabled', $customer->portal_enabled))>
                    <x-input-label for="portal_enabled" :value="__('customer.portal_enabled')" class="ml-2" />
                </div>

                <div class="flex items-center">
                    <input type="hidden" name="portal_show_gps" value="0">
                    <input id="portal_show_gps" name="portal_show_gps" type="checkbox" value="1"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                           @checked(old('portal_show_gps', $customer->portal_show_gps))>
                    <x-input-label for="portal_show_gps" :value="__('customer.portal_show_gps')" class="ml-2" />
                </div>

                <div class="flex items-center">
                    <input type="hidden" name="portal_show_photos" value="0">
                    <input id="portal_show_photos" name="portal_show_photos" type="checkbox" value="1"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                           @checked(old('portal_show_photos', $customer->portal_show_photos))>
                    <x-input-label for="portal_show_photos" :value="__('customer.portal_show_photos')" class="ml-2" />
                </div>

                <div class="flex items-center">
                    <input type="hidden" name="portal_show_driver_name" value="0">
                    <input id="portal_show_driver_name" name="portal_show_driver_name" type="checkbox" value="1"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                           @checked(old('portal_show_driver_name', $customer->portal_show_driver_name))>
                    <x-input-label for="portal_show_driver_name" :value="__('customer.portal_show_driver_name')" class="ml-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-primary-button>{{ __('ui.button_save') }}</x-primary-button>
            </div>
        </form>
    @endif
</div>
