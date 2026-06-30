<?php

namespace Tests\Feature\Extension;

use App\Services\Extension\JobTypeRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExampleModuleJobTypeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        putenv('EXAMPLE_MODULE_ENABLED');
        unset($_ENV['EXAMPLE_MODULE_ENABLED']);
        parent::tearDown();
    }

    public function test_example_module_registers_a_demo_job_type(): void
    {
        // Register the module autoloader (not in composer autoload — loaded by ModuleManager at runtime).
        $modulePath = base_path('modules/example/src');
        spl_autoload_register(function (string $class) use ($modulePath) {
            $prefix = 'Schneespur\\Module\\Example\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $modulePath . '/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });

        // Boot the example module explicitly, mirroring how ModuleManager boots enabled modules.
        putenv('EXAMPLE_MODULE_ENABLED=true');
        $_ENV['EXAMPLE_MODULE_ENABLED'] = true;

        $this->app->register(\Schneespur\Module\Example\ExampleServiceProvider::class);

        $this->assertTrue(app(JobTypeRegistry::class)->hasType('gruenpflege'));
    }
}
