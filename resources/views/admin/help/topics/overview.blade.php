<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('help.topic_overview') }}</h2>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.overview_daily_title') }}</h3>
        <p>{{ __('help.overview_daily_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.overview_monthly_title') }}</h3>
        <p>{{ __('help.overview_monthly_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.overview_driver_title') }}</h3>
        <p>{{ __('help.overview_driver_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.overview_time_title') }}</h3>
        <p>{{ __('help.overview_time_text', ['app_name' => brand()]) }}</p>
    </div>
</div>
