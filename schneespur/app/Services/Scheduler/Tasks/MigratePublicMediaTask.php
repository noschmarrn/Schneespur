<?php

namespace App\Services\Scheduler\Tasks;

use App\Models\Setting;
use App\Services\Scheduler\ScheduledTaskInterface;
use App\Services\Storage\PublicMediaMigrator;
use Illuminate\Support\Facades\Log;

/**
 * Carries the M018/S04 media migration on hosts without a CLI.
 *
 * One bounded batch per run, so a shared-hosting PHP time limit cannot leave
 * the job half-done in a way that matters — the migrator is repeatable. Once
 * nothing is pending the task marks itself finished and stops scanning.
 */
class MigratePublicMediaTask implements ScheduledTaskInterface
{
    /** Set once the last legacy file has moved; the only state this task keeps. */
    public const DONE_SETTING = 'media_migration_done';

    private const BATCH_SIZE = 50;

    public function slug(): string
    {
        return 'migrate-public-media';
    }

    public function label(): string
    {
        return __('scheduler.task_migrate_public_media');
    }

    public function schedule(): string
    {
        return '*/15 * * * *';
    }

    public function handle(): void
    {
        $result = app(PublicMediaMigrator::class)->migrateBatch(self::BATCH_SIZE);

        if ($result['moved'] > 0) {
            Log::info('MigratePublicMediaTask: batch moved', $result);
        }

        if ($result['remaining'] === 0) {
            Setting::set(self::DONE_SETTING, true, 'bool');
        }
    }

    /**
     * A settings lookup rather than a directory scan: this is asked on every
     * scheduler tick, forever, long after the migration is over.
     */
    public function isEnabled(): bool
    {
        return Setting::get(self::DONE_SETTING) !== true;
    }

    public function source(): string
    {
        return 'core';
    }
}
