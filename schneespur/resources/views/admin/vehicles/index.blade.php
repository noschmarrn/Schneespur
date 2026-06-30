<x-admin-layout>
    <x-slot name="header">{{ __('vehicle.page_list') }} <x-help-icon topic="first-steps" /></x-slot>

    <div class="flex items-center justify-end">
        <a href="{{ route('admin.vehicles.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('vehicle.btn_create') }}
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.vehicles.index') }}" class="mt-4">
        <x-text-input name="search" :value="request('search')" :placeholder="__('ui.search_placeholder')" class="w-full sm:w-64" />
    </form>

    <div class="mt-6">
        @if ($vehicles->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('vehicle.col_name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('vehicle.col_license') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('vehicle.col_owntracks_device_id') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('vehicle.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($vehicles as $vehicle)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $vehicle->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $vehicle->license_plate }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $vehicle->owntracks_device_id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('ui.button_edit') }}</a>

                                        <button x-data x-on:click="$dispatch('open-modal', 'delete-vehicle-{{ $vehicle->id }}')" type="button" class="ml-3 text-red-600 hover:text-red-900">
                                            {{ __('ui.button_delete') }}
                                        </button>

                                        <x-confirm-dialog
                                            :name="'delete-vehicle-' . $vehicle->id"
                                            :title="__('vehicle.modal_delete_title')"
                                            :message="__('vehicle.modal_delete_body1', ['name' => $vehicle->name])"
                                        >
                                            <x-slot name="action">
                                                <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-danger-button>{{ __('vehicle.modal_delete_submit') }}</x-danger-button>
                                                </form>
                                            </x-slot>
                                        </x-confirm-dialog>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $vehicles->links() }}
            </div>
        @else
            <x-empty-state :heading="__('vehicle.empty_heading')" :body="__('vehicle.empty_body')">
                <x-slot name="action">
                    <a href="{{ route('admin.vehicles.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('vehicle.empty_cta') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @endif
    </div>
</x-admin-layout>
