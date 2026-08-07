<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleKillSwitchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SLUG = 'killswitch-probe';

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path('modules/' . self::SLUG);
        File::ensureDirectoryExists($path);
        File::put($path . '/module.json', json_encode([
            'name' => ['de' => 'Probe', 'en' => 'Probe'],
            'version' => '1.0.0',
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/' . self::SLUG));

        parent::tearDown();
    }

    private function freshManager(): ModuleManager
    {
        $manager = new ModuleManager(app());
        $manager->discover();

        return $manager;
    }

    public function test_module_without_db_row_does_not_boot(): void
    {
        $this->assertFalse($this->freshManager()->isEnabled(self::SLUG));
    }

    public function test_module_disabled_in_db_does_not_boot(): void
    {
        Module::create([
            'slug' => self::SLUG,
            'version' => '1.0.0',
            'enabled' => false,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $this->assertFalse($this->freshManager()->isEnabled(self::SLUG));
    }

    public function test_module_enabled_in_db_boots(): void
    {
        Module::create([
            'slug' => self::SLUG,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $this->assertTrue($this->freshManager()->isEnabled(self::SLUG));
    }

    public function test_unknown_slug_is_not_enabled(): void
    {
        $this->assertFalse($this->freshManager()->isEnabled('does-not-exist'));
    }

    public function test_persisted_state_is_read_with_a_single_query(): void
    {
        Module::create([
            'slug' => self::SLUG,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $manager = $this->freshManager();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        // Ten lookups must not cost ten queries. isEnabled() runs once per
        // discovered module on every request, so this has to stay constant.
        for ($i = 0; $i < 10; $i++) {
            $manager->isEnabled(self::SLUG);
        }

        $this->assertLessThanOrEqual(1, $queries);
    }

    public function test_disable_takes_effect_within_the_same_request(): void
    {
        Module::create([
            'slug' => self::SLUG,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $manager = $this->freshManager();
        $this->assertTrue($manager->isEnabled(self::SLUG));

        $manager->disable(self::SLUG);

        $this->assertFalse($manager->isEnabled(self::SLUG));
    }

    public function test_writing_the_db_flag_invalidates_the_cached_state(): void
    {
        $module = Module::create([
            'slug' => self::SLUG,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        // The singleton is what boot() and every module listener sees.
        $manager = app(ModuleManager::class);
        $manager->discover();

        $this->assertTrue($manager->isEnabled(self::SLUG));

        // AdminModuleController writes the flag straight through the model,
        // never via ModuleManager::disable(). The cache has to notice anyway.
        $module->update(['enabled' => false]);

        $this->assertFalse($manager->isEnabled(self::SLUG));
    }

    public function test_missing_modules_table_resolves_to_default_deny(): void
    {
        Module::create([
            'slug' => self::SLUG,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);

        $manager = $this->freshManager();

        // Mirrors a fresh install / mid-migration state: the table the manager
        // asks for is not there yet. That must default-deny, not throw into boot().
        DB::statement('DROP TABLE modules');

        $this->assertFalse($manager->isEnabled(self::SLUG));
    }
}
