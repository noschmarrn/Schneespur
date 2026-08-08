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

    private function driver(): User
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        return $driver;
    }

    /** Distinguishes a real null from an unsaved model whose id is also null
     *  — the exact distinction the create-page contract depends on. */
    private function entityProbe(): \Closure
    {
        return fn ($html, $entity) => $html . '<p>hook-entity:' . ($entity === null ? 'null' : $entity->id) . '</p>';
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
            $this->entityProbe(),
        );

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->assertSee('hook-entity:' . $object->id);
    }

    /**
     * On create, the parent view instantiates `new CustomerObject` rather
     * than omitting the variable — an unsaved model, not an absent one. The
     * hook must still forward a literal null, because that is the branch a
     * module author will actually test for ("does this entity exist yet?").
     * `$entity?->id ?? 'none'` cannot tell the two apart (both have a null
     * id); this asserts on `$entity === null` directly.
     */
    public function test_the_hook_receives_null_when_creating(): void
    {
        $customer = Customer::create(['name' => 'Another Customer']);

        app(FilterRegistry::class)->register(
            'schneespur.admin.customer_object.form.after',
            $this->entityProbe(),
        );

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/create")
            ->assertStatus(200)
            ->assertSee('hook-entity:null');
    }

    public function test_the_driver_form_renders_the_hook(): void
    {
        $driver = $this->driver();

        app(FilterRegistry::class)->register(
            'schneespur.admin.driver.form.after',
            fn ($html, $entity) => $html . '<p>home coordinate</p>',
        );

        $this->actingAs($this->admin())
            ->get("/admin/drivers/{$driver->id}/edit")
            ->assertStatus(200)
            ->assertSee('home coordinate');
    }

    /** Same unsaved-model trap as the customer-object form (drivers/create.blade.php
     *  passes `new User`), verified independently since it is a separate partial. */
    public function test_the_driver_hook_receives_null_when_creating(): void
    {
        app(FilterRegistry::class)->register(
            'schneespur.admin.driver.form.after',
            $this->entityProbe(),
        );

        $this->actingAs($this->admin())
            ->get('/admin/drivers/create')
            ->assertStatus(200)
            ->assertSee('hook-entity:null');
    }

    /** If @filterSlot is ever glued after a word character, Blade's \B-bounded
     *  directive matcher silently fails to compile it and the literal call
     *  leaks into the page instead of being evaluated. */
    public function test_the_hook_directive_actually_compiles(): void
    {
        $object = $this->object();

        $html = $this->actingAs($this->admin())
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->getContent();

        $this->assertStringNotContainsString('@filterSlot', $html);
    }

    /** No consumer should be indistinguishable from a consumer that hands the
     *  value back unchanged — that is what "prints nothing extra" means. */
    public function test_an_unconsumed_hook_renders_identically_to_a_passthrough_consumer(): void
    {
        $object = $this->object();
        $admin = $this->admin();

        $unconsumed = $this->actingAs($admin)
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->getContent();

        app(FilterRegistry::class)->register(
            'schneespur.admin.customer_object.form.after',
            fn ($html, $entity) => $html,
        );

        $passthrough = $this->actingAs($admin)
            ->get("/admin/customers/{$object->customer_id}/objects/{$object->id}/edit")
            ->getContent();

        $this->assertSame($unconsumed, $passthrough);
    }
}
