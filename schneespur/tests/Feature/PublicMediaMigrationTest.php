<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Scheduler\Tasks\MigratePublicMediaTask;
use App\Services\Storage\MediaAccessRegistry;
use App\Services\Storage\PublicMediaMigrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        // The core already claims this in AppServiceProvider; stated here so the
        // test does not silently depend on that registration.
        app(MediaAccessRegistry::class)->register('photos/', fn () => true);
    }

    public function test_files_move_to_the_private_disk(): void
    {
        Storage::disk('public')->put('photos/a.jpg', 'A');
        Storage::disk('public')->put('photos/b.jpg', 'B');

        $result = app(PublicMediaMigrator::class)->migrateBatch(10);

        $this->assertSame(2, $result['moved']);
        $this->assertSame('A', Storage::disk('local')->get('photos/a.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('photos/a.jpg'));
    }

    public function test_a_batch_is_bounded_and_reports_the_rest(): void
    {
        foreach (range(1, 5) as $i) {
            Storage::disk('public')->put("photos/{$i}.jpg", (string) $i);
        }

        $result = app(PublicMediaMigrator::class)->migrateBatch(2);

        $this->assertSame(2, $result['moved']);
        $this->assertSame(3, $result['remaining']);
    }

    public function test_an_interrupted_run_can_simply_be_repeated(): void
    {
        foreach (range(1, 4) as $i) {
            Storage::disk('public')->put("photos/{$i}.jpg", (string) $i);
        }

        app(PublicMediaMigrator::class)->migrateBatch(2);
        $second = app(PublicMediaMigrator::class)->migrateBatch(10);

        $this->assertSame(2, $second['moved']);
        $this->assertSame(0, $second['remaining']);

        foreach (range(1, 4) as $i) {
            $this->assertSame((string) $i, Storage::disk('local')->get("photos/{$i}.jpg"));
        }
    }

    public function test_an_existing_private_copy_is_not_overwritten(): void
    {
        Storage::disk('public')->put('photos/a.jpg', 'OLD');
        Storage::disk('local')->put('photos/a.jpg', 'NEW');

        app(PublicMediaMigrator::class)->migrateBatch(10);

        $this->assertSame('NEW', Storage::disk('local')->get('photos/a.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('photos/a.jpg'));
    }

    public function test_unprotected_prefixes_are_left_alone(): void
    {
        Storage::disk('public')->put('logo/brand.png', 'LOGO');

        app(PublicMediaMigrator::class)->migrateBatch(10);

        // The logo is public by design and has no registered authorizer, so the
        // migrator must not drag it out of reach.
        $this->assertTrue(Storage::disk('public')->exists('logo/brand.png'));
    }

    public function test_nested_directories_are_migrated_too(): void
    {
        Storage::disk('public')->put('photos/2026/08/deep.jpg', 'DEEP');

        $result = app(PublicMediaMigrator::class)->migrateBatch(10);

        $this->assertSame(1, $result['moved']);
        $this->assertSame('DEEP', Storage::disk('local')->get('photos/2026/08/deep.jpg'));
    }

    public function test_nothing_pending_is_a_no_op(): void
    {
        $result = app(PublicMediaMigrator::class)->migrateBatch(10);

        $this->assertSame(0, $result['moved']);
        $this->assertSame(0, $result['remaining']);
    }

    public function test_the_scheduled_task_runs_until_nothing_is_left_then_switches_itself_off(): void
    {
        foreach (range(1, 3) as $i) {
            Storage::disk('public')->put("photos/{$i}.jpg", (string) $i);
        }

        $task = app(MigratePublicMediaTask::class);

        $this->assertTrue($task->isEnabled());

        $task->handle();

        // Everything fitted in one batch, so the task must not keep scanning
        // the filesystem on every scheduler tick for the rest of the install's
        // life.
        $this->assertFalse($task->isEnabled());
        $this->assertTrue(Setting::get(MigratePublicMediaTask::DONE_SETTING));
    }

    public function test_the_scheduled_task_stays_on_while_files_remain(): void
    {
        // More than one batch worth would be slow to fake; a stubbed migrator
        // that reports leftovers makes the same point.
        $this->app->bind(PublicMediaMigrator::class, fn () => new class (app(MediaAccessRegistry::class)) extends PublicMediaMigrator
        {
            public function migrateBatch(int $limit = 50): array
            {
                return ['moved' => $limit, 'remaining' => 7];
            }
        });

        $task = app(MigratePublicMediaTask::class);
        $task->handle();

        $this->assertTrue($task->isEnabled());
    }
}
