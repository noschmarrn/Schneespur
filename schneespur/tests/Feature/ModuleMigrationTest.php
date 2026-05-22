<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $testModuleSlug = 'test-migration-mod';
    private string $testModulePath;
    private string $testMigrationPath;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->testModulePath = base_path("modules/{$this->testModuleSlug}");
        $this->testMigrationPath = "{$this->testModulePath}/database/migrations";

        $this->createTestModuleMigration();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mod_test_migration_mod_logs');

        if (File::isDirectory($this->testModulePath)) {
            File::deleteDirectory($this->testModulePath);
        }

        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function createTestModuleMigration(): void
    {
        File::ensureDirectoryExists($this->testMigrationPath);

        $content = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_test_migration_mod_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 32)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_test_migration_mod_logs');
    }
};
PHP;

        File::put("{$this->testMigrationPath}/2026_01_01_000001_create_mod_test_migration_mod_logs_table.php", $content);
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

    public function test_enable_runs_module_migrations(): void
    {
        Module::create([
            'slug' => $this->testModuleSlug,
            'version' => '1.0.0',
            'enabled' => false,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $this->assertFalse(Schema::hasTable('mod_test_migration_mod_logs'));

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', $this->testModuleSlug));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $this->assertTrue(Schema::hasTable('mod_test_migration_mod_logs'));
    }

    public function test_enable_module_without_migrations_works_unchanged(): void
    {
        $module = Module::create([
            'slug' => 'no-migration-module',
            'version' => '1.0.0',
            'enabled' => false,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'no-migration-module'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $this->assertTrue($module->fresh()->enabled);
    }

    public function test_remove_rolls_back_module_migrations(): void
    {
        Artisan::call('migrate', [
            '--path' => "modules/{$this->testModuleSlug}/database/migrations",
            '--force' => true,
        ]);
        $this->assertTrue(Schema::hasTable('mod_test_migration_mod_logs'));

        Module::create([
            'slug' => $this->testModuleSlug,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->delete(route('admin.settings.modules.remove', $this->testModuleSlug));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $this->assertFalse(Schema::hasTable('mod_test_migration_mod_logs'));
    }

    public function test_migration_directory_detection(): void
    {
        $this->assertTrue(File::isDirectory($this->testMigrationPath));

        $phpFiles = File::glob($this->testMigrationPath . '/*.php');
        $this->assertNotEmpty($phpFiles);
    }

    public function test_module_migration_creates_expected_table_schema(): void
    {
        Artisan::call('migrate', [
            '--path' => "modules/{$this->testModuleSlug}/database/migrations",
            '--force' => true,
        ]);

        $this->assertTrue(Schema::hasTable('mod_test_migration_mod_logs'));
        $this->assertTrue(Schema::hasColumns('mod_test_migration_mod_logs', [
            'id', 'level', 'message', 'context', 'created_at', 'updated_at',
        ]));
    }

    public function test_module_migration_rollback_drops_table(): void
    {
        Artisan::call('migrate', [
            '--path' => "modules/{$this->testModuleSlug}/database/migrations",
            '--force' => true,
        ]);
        $this->assertTrue(Schema::hasTable('mod_test_migration_mod_logs'));

        Artisan::call('migrate:rollback', [
            '--path' => "modules/{$this->testModuleSlug}/database/migrations",
            '--force' => true,
            '--step' => 999,
        ]);
        $this->assertFalse(Schema::hasTable('mod_test_migration_mod_logs'));
    }
}
