<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupTargetInterface;
use App\Services\Backup\BackupTargetRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackupSettingsTest extends TestCase
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

    private function createAdmin(string $email = 'admin@test.local'): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function createDriver(string $email = 'driver@test.local'): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        return $user->fresh();
    }

    public function test_admin_can_view_backup_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.backup'));

        $response->assertOk();
        $response->assertSee(__('backup.settings_title'));
        $response->assertSee('local');
    }

    public function test_admin_can_change_backup_target(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.backup.update'), [
            'backup_target' => 'local',
        ]);

        $response->assertRedirect(route('admin.settings.backup'));
        $response->assertSessionHas('success');
        $this->assertSame('local', Setting::get('backup_target', 'local'));
    }

    public function test_admin_can_select_custom_registered_target(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);
        $registry->register('test-custom', TestBackupTarget::class);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.backup.update'), [
            'backup_target' => 'test-custom',
        ]);

        $response->assertRedirect(route('admin.settings.backup'));
        $response->assertSessionHas('success');
        $this->assertSame('test-custom', Setting::get('backup_target'));
    }

    public function test_driver_cannot_access_backup_settings(): void
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($driver)->get(route('admin.settings.backup'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_driver_cannot_update_backup_settings(): void
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($driver)->post(route('admin.settings.backup.update'), [
            'backup_target' => 'local',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_validation_rejects_invalid_slug(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.backup.update'), [
            'backup_target' => 'nonexistent-target',
        ]);

        $response->assertSessionHasErrors('backup_target');
    }

    public function test_validation_rejects_empty_target(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.backup.update'), [
            'backup_target' => '',
        ]);

        $response->assertSessionHasErrors('backup_target');
    }

    public function test_page_shows_available_targets(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);
        $registry->register('test-s3', TestBackupTarget::class);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.backup'));

        $response->assertOk();
        $response->assertSee('Test Target');
    }

    public function test_settings_index_shows_backup_card(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee(__('backup.settings_title'));
        $response->assertSee(__('backup.settings_description'));
    }
}

class TestBackupTarget implements BackupTargetInterface
{
    public function slug(): string
    {
        return 'test-custom';
    }

    public function label(): string
    {
        return 'Test Target';
    }

    public function store(string $sourcePath): bool
    {
        return true;
    }

    public function restore(string $identifier, string $destinationPath): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
