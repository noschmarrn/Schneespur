<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('help.topic_first_steps') }}</h2>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.first_steps_company_title') }}</h3>
        <p>{{ __('help.first_steps_company_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.first_steps_drivers_title') }}</h3>
        <p>{{ __('help.first_steps_drivers_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.first_steps_customers_title') }}</h3>
        <p>{{ __('help.first_steps_customers_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.first_steps_owntracks_title') }}</h3>
        <p>{{ __('help.first_steps_owntracks_text', ['app_name' => brand()]) }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.first_steps_first_job_title') }}</h3>
        <p>{{ __('help.first_steps_first_job_text', ['app_name' => brand()]) }}</p>
    </div>
</div>
