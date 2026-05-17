<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('help.topic_installation') }}</h2>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.installation_requirements_title') }}</h3>
        <p>{{ __('help.installation_requirements_text', ['app_name' => brand()]) }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.installation_upload_title') }}</h3>
        <p>{{ __('help.installation_upload_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.installation_database_title') }}</h3>
        <p>{{ __('help.installation_database_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.installation_env_title') }}</h3>
        <p>{{ __('help.installation_env_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.installation_finish_title') }}</h3>
        <p>{{ __('help.installation_finish_text') }}</p>
    </div>
</div>
