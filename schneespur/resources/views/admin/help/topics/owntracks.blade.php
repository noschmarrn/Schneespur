<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('help.topic_owntracks') }}</h2>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.owntracks_intro_title') }}</h3>
        <p>{{ __('help.owntracks_intro_text', ['app_name' => brand()]) }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.owntracks_install_title') }}</h3>
        <p>{{ __('help.owntracks_install_text') }}</p>

        <div class="flex flex-wrap gap-3 not-prose">
            <a href="https://play.google.com/store/apps/details?id=org.owntracks.android"
               target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-700 transition-colors">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-1.4l2.834 1.639a1 1 0 010 1.726l-2.834 1.64-2.532-2.533 2.532-2.472zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/>
                </svg>
                Google Play
            </a>
            <a href="https://apps.apple.com/de/app/owntracks/id692424691"
               target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-700 transition-colors">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                </svg>
                App Store
            </a>
        </div>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.owntracks_config_title') }}</h3>
        <p>{{ __('help.owntracks_config_text', ['app_name' => brand()]) }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.owntracks_qr_title') }}</h3>
        <p>{{ __('help.owntracks_qr_text') }}</p>

        <h3 class="text-lg font-semibold text-gray-800">{{ __('help.owntracks_troubleshoot_title') }}</h3>
        <p>{{ __('help.owntracks_troubleshoot_text') }}</p>
    </div>
</div>
