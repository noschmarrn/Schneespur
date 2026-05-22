<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ModuleManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->manager = app(ModuleManager::class);
    }

    protected function tearDown(): void
    {
        Setting::where('key', 'like', 'testmod.%')->delete();
        Setting::where('key', 'like', 'nonexistent.%')->delete();
        Schema::dropIfExists('mod_settings_test_logs');

        $testModulePath = base_path('modules/settings-test-mod');
        if (File::isDirectory($testModulePath)) {
            File::deleteDirectory($testModulePath);
        }

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

    public function test_register_settings_writes_defaults_to_database(): void
    {
        $this->manager->registerSettings('testmod', [
            'greeting' => 'Hello World',
            'max_items' => 42,
            'debug' => true,
        ]);

        $this->assertSame('Hello World', Setting::get('testmod.greeting'));
        $this->assertSame(42, Setting::get('testmod.max_items'));
        $this->assertTrue(Setting::get('testmod.debug'));
    }

    public function test_register_settings_does_not_overwrite_existing(): void
    {
        Setting::set('testmod.greeting', 'Custom Value', 'string');

        $this->manager->registerSettings('testmod', [
            'greeting' => 'Default Value',
            'other' => 'new',
        ]);

        $this->assertSame('Custom Value', Setting::get('testmod.greeting'));
        $this->assertSame('new', Setting::get('testmod.other'));
    }

    public function test_cleanup_settings_deletes_all_with_prefix(): void
    {
        $this->manager->registerSettings('testmod', [
            'a' => '1',
            'b' => '2',
            'c' => '3',
        ]);

        $this->assertSame(3, Setting::where('key', 'like', 'testmod.%')->count());

        $deleted = $this->manager->cleanupSettings('testmod');

        $this->assertSame(3, $deleted);
        $this->assertSame(0, Setting::where('key', 'like', 'testmod.%')->count());
    }

    public function test_cleanup_settings_with_nonexistent_prefix_returns_zero(): void
    {
        $deleted = $this->manager->cleanupSettings('nonexistent');

        $this->assertSame(0, $deleted);
    }

    public function test_remove_flow_cleans_up_settings_after_rollback(): void
    {
        $slug = 'settings-test-mod';
        $modulePath = base_path("modules/{$slug}");
        $migrationPath = "{$modulePath}/database/migrations";

        File::ensureDirectoryExists($migrationPath);
        File::put("{$migrationPath}/2026_01_01_000001_create_mod_settings_test_logs_table.php", <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_settings_test_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_settings_test_logs');
    }
};
PHP);

        Artisan::call('migrate', [
            '--path' => "modules/{$slug}/database/migrations",
            '--force' => true,
        ]);

        $module = Module::create([
            'slug' => $slug,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $this->manager->registerSettings($slug, [
            'greeting' => 'Hello',
            'mode' => 'test',
        ]);

        $this->assertTrue(Schema::hasTable('mod_settings_test_logs'));
        $this->assertSame('Hello', Setting::get("{$slug}.greeting"));

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->delete(route('admin.settings.modules.remove', $slug));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');

        $this->assertFalse(Schema::hasTable('mod_settings_test_logs'));
        $this->assertNull(Setting::get("{$slug}.greeting"));
        $this->assertNull(Setting::get("{$slug}.mode"));
    }
}
