<div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <div class="text-sm font-medium text-gray-500">{{ __('admin.stat_customers') }}</div>
        <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $widget['data']['customerCount'] }}</div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <div class="text-sm font-medium text-gray-500">{{ __('admin.stat_drivers') }}</div>
        <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $widget['data']['driverCount'] }}</div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
        <div class="text-sm font-medium text-gray-500">{{ __('admin.stat_vehicles') }}</div>
        <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $widget['data']['vehicleCount'] }}</div>
    </div>
</div>
