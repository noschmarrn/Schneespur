<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use App\Services\Extension\FilterRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFormHookTest extends TestCase
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

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function object(): CustomerObject
    {
        $customer = Customer::create(['name' => 'Test Customer']);

        return CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);
    }

    public function test_the_customer_object_form_renders_the_hook(): void
    {
        app(FilterRegistry::class)->register(
            'schneespur.admin.customer_object.form.after',
            fn ($html, $object) => $html . '<p id="module-field">geofence radius</p>',
        );

        $object = $this->object();

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->assertStatus(200)
            ->assertSee('geofence radius');
    }

    /** The whole reason this is a filter and not a slot. */
    public function test_the_hook_receives_the_edited_entity(): void
    {
        $object = $this->object();

        app(FilterRegistry::class)->register(
            'schneespur.admin.customer_object.form.after',
            fn ($html, $entity) => $html . '<p>id:' . ($entity?->id ?? 'none') . '</p>',
        );

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->assertSee('id:' . $object->id);
    }

    /** On create there is no entity yet — the hook must cope, not crash. */
    public function test_the_hook_receives_null_when_creating(): void
    {
        $customer = Customer::create(['name' => 'Another Customer']);

        app(FilterRegistry::class)->register(
            'schneespur.admin.customer_object.form.after',
            fn ($html, $entity) => $html . '<p>id:' . ($entity?->id ?? 'none') . '</p>',
        );

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/create")
            ->assertStatus(200)
            ->assertSee('id:none');
    }

    public function test_the_driver_form_renders_the_hook(): void
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        app(FilterRegistry::class)->register(
            'schneespur.admin.driver.form.after',
            fn ($html, $entity) => $html . '<p>home coordinate</p>',
        );

        $this->actingAs($this->admin())
            ->get("/admin/drivers/{$driver->id}/edit")
            ->assertStatus(200)
            ->assertSee('home coordinate');
    }

    /** No consumer, no output — an empty hook must not print anything. */
    public function test_an_unused_hook_prints_nothing(): void
    {
        $object = $this->object();

        $html = $this->actingAs($this->admin())
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->getContent();

        $this->assertStringNotContainsString('Array', $html);
    }
}
