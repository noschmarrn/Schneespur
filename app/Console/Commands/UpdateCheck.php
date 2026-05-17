<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SchneespurUpdater;
use Illuminate\Console\Command;

class UpdateCheck extends Command
{
    protected $signature = 'schneespur:update-check
        {--apply : Download and verify the ZIP after finding an update}';

    protected $description = 'Check the update server for a new Schneespur version.';

    public function handle(): int
    {
        if (! $this->option('apply') && ! Setting::get('auto_update_check', true)) {
            return 0;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            $this->error(__('update.sodium_missing'));

            return 1;
        }

        try {
            $updater  = new SchneespurUpdater;
            $manifest = $updater->checkForUpdate();
        } catch (\Throwable $e) {
            $this->error(__('update.artisan_check_failed', ['error' => $e->getMessage()]));

            return 1;
        }

        if ($manifest === null) {
            $this->info(__('update.artisan_up_to_date'));

            return 0;
        }

        $this->info(__('update.artisan_update_available', [
            'version'   => $manifest['version'],
            'counter'   => $manifest['counter'],
            'signed_at' => $manifest['signed_at'],
        ]));

        if (! $this->option('apply')) {
            $this->line(__('update.artisan_apply_hint'));

            return 0;
        }

        try {
            $zipPath = $updater->downloadAndVerifyZip($manifest);
        } catch (\Throwable $e) {
            $this->error(__('update.artisan_zip_failed', ['error' => $e->getMessage()]));

            return 1;
        }

        $this->info(__('update.artisan_zip_verified', ['path' => $zipPath]));

        return 0;
    }
}
