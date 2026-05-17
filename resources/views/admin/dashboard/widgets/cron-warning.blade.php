<div id="cron-setup" class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-md">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-400 mt-0.5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
        <div>
            <p class="text-sm font-medium text-yellow-800">{{ __('dashboard.cron_warning_title') }}</p>
            <p class="mt-1 text-sm text-yellow-700">{{ __('dashboard.cron_warning_text') }}</p>
            @php
                $phpBin = PHP_BINARY;
                if (str_contains($phpBin, 'fpm') || str_contains($phpBin, 'cgi')) {
                    $ver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
                    foreach (["/usr/bin/php{$ver}", '/usr/bin/php', '/usr/local/bin/php'] as $c) {
                        if (is_executable($c)) { $phpBin = $c; break; }
                    }
                }
            @endphp
            <p class="mt-2 text-xs font-mono text-yellow-600 bg-yellow-100 rounded px-2 py-1 inline-block">* * * * * {{ $phpBin }} {{ base_path('artisan') }} schedule:run >> /dev/null 2>&1</p>
        </div>
    </div>
</div>
