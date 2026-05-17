<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\RetentionService;
use Illuminate\Console\Command;

class PurgeExpiredJobs extends Command
{
    protected $signature = 'jobs:retention-delete
        {--dry-run : Show what would be deleted without deleting}
        {--limit=50 : Maximum jobs to delete per run}';

    protected $description = 'Delete expired jobs after the configured retention period, preserving monthly aggregates.';

    public function handle(RetentionService $retentionService): int
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if (! $isDryRun && ! Setting::get('retention_auto_delete', false)) {
            $this->info('Auto-Löschung ist deaktiviert.');

            return 0;
        }

        $jobs = $retentionService->getExpiredJobs($limit);

        if ($jobs->isEmpty()) {
            $this->info('Keine abgelaufenen Einsätze.');

            return 0;
        }

        if ($isDryRun) {
            $this->info("Folgende Einsätze würden gelöscht ({$jobs->count()}):");
            $this->table(
                ['ID', 'Kunde', 'Beendet am'],
                $jobs->map(fn ($job) => [
                    $job->id,
                    $job->customer?->name ?? '–',
                    $job->ended_at->format('d.m.Y H:i'),
                ]),
            );

            return 0;
        }

        $deleted = $retentionService->purge($limit);

        $this->info("{$deleted} Einsätze gelöscht und in Monatsstatistik aggregiert.");

        if ($deleted < $jobs->count()) {
            $failed = $jobs->count() - $deleted;
            $this->warn("{$failed} Einsätze konnten nicht gelöscht werden. Siehe Laravel-Log.");
        }

        return 0;
    }
}
