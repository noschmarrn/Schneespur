<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\Artisan;

class StorageConfigurator
{
    private const SUCCESS_MESSAGES = [
        'storage:link' => 'install.storage_link_success',
        'config:cache' => 'install.storage_config_cache_success',
        'view:cache' => 'install.storage_view_cache_success',
    ];

    public function runAll(): array
    {
        $results = [];

        try {
            $exitCode = Artisan::call('storage:link');
            $results[] = [
                'command' => 'storage:link',
                'success' => $exitCode === 0,
                'output' => $exitCode === 0
                    ? __('install.storage_link_success')
                    : trim(Artisan::output()),
            ];
        } catch (\Exception $e) {
            $results[] = [
                'command' => 'storage:link',
                'success' => false,
                'output' => $e->getMessage(),
                'fallback' => true,
            ];
        }

        $commands = ['config:cache', 'view:cache'];

        foreach ($commands as $command) {
            try {
                $exitCode = Artisan::call($command);
                $results[] = [
                    'command' => $command,
                    'success' => $exitCode === 0,
                    'output' => $exitCode === 0
                        ? __(self::SUCCESS_MESSAGES[$command])
                        : trim(Artisan::output()),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'command' => $command,
                    'success' => false,
                    'output' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
