<?php

namespace App\Console\Commands;

use App\Services\SchneespurUpdater;
use Illuminate\Console\Command;

class UpdateRecover extends Command
{
    protected $signature = 'schneespur:update-recover
        {--force : Skip confirmation prompt}';

    protected $description = 'Recover from a failed Schneespur update (restore backup + exit maintenance mode).';

    public function handle(): int
    {
        $updater  = new SchneespurUpdater;
        $recovery = $updater->getRecoveryInfo();

        if ($recovery === null) {
            $this->info(__('update.recovery_no_info'));

            if (app()->isDownForMaintenance()) {
                $this->warn(__('update.recovery_still_maintenance'));
                if ($this->option('force') || $this->confirm(__('update.recovery_confirm_up'))) {
                    \Illuminate\Support\Facades\Artisan::call('up');
                    $this->info(__('update.recovery_maintenance_disabled'));
                }
            }

            return 0;
        }

        $this->error(__('update.recovery_found'));
        $this->table(
            ['Key', 'Value'],
            [
                ['Failed At', $recovery['failed_at'] ?? '?'],
                ['Target Version', $recovery['target_version'] ?? '?'],
                ['Error', $recovery['error'] ?? '?'],
                ['Backup Dir', $recovery['backup_dir'] ?? '?'],
            ]
        );

        if (! empty($recovery['recovery_steps'])) {
            $this->line('');
            $this->info(__('update.recovery_steps_title'));
            foreach ($recovery['recovery_steps'] as $step) {
                $this->line("  {$step}");
            }
        }

        $backupDir = $recovery['backup_dir'] ?? null;

        if ($backupDir && is_dir($backupDir)) {
            $this->line('');
            if ($this->option('force') || $this->confirm(__('update.recovery_confirm_restore'))) {
                $ok = $updater->restoreFromBackup($backupDir);
                if ($ok) {
                    $this->info(__('update.recovery_restore_success'));
                } else {
                    $this->error(__('update.recovery_restore_failed'));

                    return 1;
                }
            }
        } else {
            $this->warn(__('update.recovery_no_backup'));
        }

        if (app()->isDownForMaintenance()) {
            if ($this->option('force') || $this->confirm(__('update.recovery_confirm_up'))) {
                \Illuminate\Support\Facades\Artisan::call('up');
                $this->info(__('update.recovery_maintenance_disabled'));
            }
        }

        $updater->clearRecoveryInfo();
        $this->info(__('update.recovery_cleared'));

        return 0;
    }
}
