<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeModuleLogs extends Command
{
    protected $signature = 'modlogs:purge
        {--days=7 : Days to keep}
        {--dry-run : Show count without deleting}';

    protected $description = 'Delete module log entries older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $query = DB::table('mod_logs')->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = $query->count();

            if ($count === 0) {
                $this->info('Keine alten Modul-Logs.');
                return 0;
            }

            $this->info("{$count} Modul-Logs würden gelöscht.");
            return 0;
        }

        $deleted = $query->delete();

        if ($deleted === 0) {
            $this->info('Keine alten Modul-Logs.');
            return 0;
        }

        $this->info("{$deleted} Modul-Logs gelöscht.");
        return 0;
    }
}
