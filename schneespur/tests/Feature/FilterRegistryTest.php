<?php

namespace Tests\Feature;

use App\Services\Extension\FilterRegistry;
use App\Services\Extension\NavigationRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FilterRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FilterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FilterRegistry;
    }

    public function test_apply_returns_original_value_when_no_filters_registered(): void
    {
        $result = $this->registry->apply('schneespur.nonexistent.hook', ['a', 'b']);

        $this->assertSame(['a', 'b'], $result);
    }

    public function test_filters_execute_in_priority_order(): void
    {
        $order = [];

        $this->registry->register('test.hook', function ($value) use (&$order) {
            $order[] = 'priority-200';
            return $value;
        }, 200);

        $this->registry->register('test.hook', function ($value) use (&$order) {
            $order[] = 'priority-50';
            return $value;
        }, 50);

        $this->registry->register('test.hook', function ($value) use (&$order) {
            $order[] = 'priority-100';
            return $value;
        }, 100);

        $this->registry->apply('test.hook', 'start');

        $this->assertSame(['priority-50', 'priority-100', 'priority-200'], $order);
    }

    public function test_equal_priority_preserves_registration_order(): void
    {
        $order = [];

        $this->registry->register('test.hook', function ($value) use (&$order) {
            $order[] = 'first';
            return $value;
        }, 100);

        $this->registry->register('test.hook', function ($value) use (&$order) {
            $order[] = 'second';
            return $value;
        }, 100);

        $this->registry->register('test.hook', function ($value) use (&$order) {
            $order[] = 'third';
            return $value;
        }, 100);

        $this->registry->apply('test.hook', 'start');

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function test_apply_passes_context_to_callbacks(): void
    {
        $receivedContext = [];

        $this->registry->register('test.hook', function ($value, $ctxA, $ctxB) use (&$receivedContext) {
            $receivedContext = [$ctxA, $ctxB];
            return $value;
        });

        $this->registry->apply('test.hook', 'value', 'context-a', 'context-b');

        $this->assertSame(['context-a', 'context-b'], $receivedContext);
    }

    public function test_throwing_callback_logs_warning_and_preserves_previous_value(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'FilterRegistry: callback failed'
                    && $context['hook'] === 'test.hook'
                    && str_contains($context['error'], 'Intentional test exception');
            });

        $this->registry->register('test.hook', function (array $value) {
            $value[] = 'before-error';
            return $value;
        }, 10);

        $this->registry->register('test.hook', function ($value) {
            throw new \RuntimeException('Intentional test exception');
        }, 20);

        $this->registry->register('test.hook', function (array $value) {
            $value[] = 'after-error';
            return $value;
        }, 30);

        $result = $this->registry->apply('test.hook', []);

        $this->assertSame(['before-error', 'after-error'], $result);
    }

    public function test_callbacks_can_transform_value_through_chain(): void
    {
        $this->registry->register('test.hook', function (int $value) {
            return $value + 10;
        }, 10);

        $this->registry->register('test.hook', function (int $value) {
            return $value * 2;
        }, 20);

        $result = $this->registry->apply('test.hook', 5);

        $this->assertSame(30, $result);
    }

    public function test_single_callback_executes(): void
    {
        $this->registry->register('test.hook', function (array $items) {
            $items[] = 'added';
            return $items;
        });

        $result = $this->registry->apply('test.hook', ['original']);

        $this->assertSame(['original', 'added'], $result);
    }

    public function test_navigation_items_hook_is_applied(): void
    {
        $this->bootExampleModule();

        $nav = $this->app->make(NavigationRegistry::class);
        $nav->addGroup('modules', 'Modules', 100);

        $items = $nav->getItems();

        $allSlugs = [];
        foreach ($items as $groupItems) {
            foreach ($groupItems as $item) {
                $allSlugs[] = $item['slug'];
            }
        }

        $this->assertContains('example-filter', $allSlugs);
    }

    public function test_dashboard_kpis_hook_is_applied(): void
    {
        $this->bootExampleModule();

        $filterRegistry = $this->app->make(FilterRegistry::class);

        $widgets = $filterRegistry->apply('schneespur.dashboard.kpis', [
            ['key' => 'original-widget', 'label' => 'Original'],
        ]);

        $keys = array_column($widgets, 'key');

        $this->assertContains('example-filter-widget', $keys);
    }

    private function bootExampleModule(): void
    {
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

        putenv('EXAMPLE_MODULE_ENABLED=true');
        $_ENV['EXAMPLE_MODULE_ENABLED'] = true;

        $this->app->register(\Schneespur\Module\Example\ExampleServiceProvider::class);
    }
}
