<x-admin-layout>
    <x-slot name="header">{{ __('driver.page_archived') }} <x-help-icon topic="drivers" /></x-slot>

    <x-breadcrumb :items="[
        ['label' => __('admin.nav_drivers'), 'url' => route('admin.drivers.index')],
        ['label' => __('driver.page_archived')],
    ]" />

    <div class="mt-6">
        @if ($drivers->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.col_name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.col_anonymized_at') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('driver.col_reason') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($drivers as $driver)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $driver->displayName() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $driver->anonymized_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $driver->anonymization_reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $drivers->links() }}
            </div>
        @else
            <x-empty-state :heading="__('driver.empty_archived_heading')" :body="__('driver.empty_archived_body')" />
        @endif
    </div>
</x-admin-layout>
