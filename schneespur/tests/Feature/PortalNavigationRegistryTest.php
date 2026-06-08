<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Extension\PortalNavigationRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PortalNavigationRegistryTest extends TestCase
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

    public function test_items_are_returned_sorted_by_order(): void
    {
        $reg = new PortalNavigationRegistry;
        $reg->addItem('b', 'label.b', 'portal.home', order: 20);
        $reg->addItem('a', 'label.a', 'portal.home', order: 10);

        $items = $reg->getItems();

        $this->assertSame(['a', 'b'], array_column($items, 'slug'));
    }

    public function test_active_pattern_defaults_to_route(): void
    {
        $reg = new PortalNavigationRegistry;
        $reg->addItem('x', 'label.x', 'portal.jobs.index');

        $items = $reg->getItems();

        $this->assertSame('portal.jobs.index', $items[0]['active_pattern']);
    }

    public function test_condition_closure_filters_by_customer(): void
    {
        $reg = new PortalNavigationRegistry;
        $reg->addItem('always', 'label.always', 'portal.home');
        $reg->addItem('never', 'label.never', 'portal.home', condition: fn (Customer $c) => false);

        $customer = new Customer(['name' => 'Test']);
        $items = $reg->getItems($customer);

        $this->assertSame(['always'], array_column($items, 'slug'));
    }
}
