<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('help.topic_dsgvo') }}</h2>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.dsgvo_briefing_title') }}</h3>
        <p>{{ __('help.dsgvo_briefing_text', ['app_name' => brand()]) }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.dsgvo_confirmations_title') }}</h3>
        <p>{{ __('help.dsgvo_confirmations_text', ['app_name' => brand()]) }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.dsgvo_anonymize_title') }}</h3>
        <p>{{ __('help.dsgvo_anonymize_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.dsgvo_retention_title') }}</h3>
        <p>{{ __('help.dsgvo_retention_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.dsgvo_deletion_title') }}</h3>
        <p>{{ __('help.dsgvo_deletion_text') }}</p>
    </div>
</div>
