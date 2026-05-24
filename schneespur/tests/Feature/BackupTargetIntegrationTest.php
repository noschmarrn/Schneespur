<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupTargetRegistry;
use App\Services\Backup\LocalBackupTarget;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Stubs\FakeS3BackupTarget;
use Tests\TestCase;

class BackupTargetIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    public function test_module_registers_custom_target_and_it_resolves(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);

        $registry->register('s3', FakeS3BackupTarget::class);

        Setting::set('backup_target', 's3');

        $target = $registry->resolve();

        $this->assertInstanceOf(FakeS3BackupTarget::class, $target);
        $this->assertSame('s3', $target->slug());
        $this->assertSame('Amazon S3', $target->label());
        $this->assertTrue($target->isConfigured());
    }

    public function test_admin_settings_page_shows_registered_targets(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);
        $registry->register('s3', FakeS3BackupTarget::class);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.backup'));

        $response->assertOk();
        $response->assertSee('local');
        $response->assertSee('Amazon S3');
    }

    public function test_admin_can_switch_to_registered_custom_target(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);
        $registry->register('s3', FakeS3BackupTarget::class);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.backup.update'), [
            'backup_target' => 's3',
        ]);

        $response->assertRedirect(route('admin.settings.backup'));
        $response->assertSessionHas('success');
        $this->assertSame('s3', Setting::get('backup_target'));
    }

    public function test_full_lifecycle_register_select_resolve(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);
        $registry->register('s3', FakeS3BackupTarget::class);

        $this->assertInstanceOf(LocalBackupTarget::class, $registry->resolve());

        $admin = $this->createAdmin();
        $this->actingAs($admin)->post(route('admin.settings.backup.update'), [
            'backup_target' => 's3',
        ]);

        $resolved = $registry->resolve();
        $this->assertInstanceOf(FakeS3BackupTarget::class, $resolved);
        $this->assertSame('s3', $registry->activeSlug());
    }
}
