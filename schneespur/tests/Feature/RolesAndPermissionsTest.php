<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Extension\PermissionRegistry;
use App\Services\Extension\RoleTemplateRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
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

    private function createUser(string $roleSlug, string $email = null): User
    {
        $email ??= $roleSlug . '@test.local';
        $user = User::create([
            'name' => ucfirst($roleSlug) . ' User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::from($roleSlug);
        $user->save();

        return $user->fresh();
    }

    // --- Migration correctness ---

    public function test_core_roles_exist_after_migration(): void
    {
        $admin = Role::where('slug', 'admin')->first();
        $driver = Role::where('slug', 'driver')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($driver);
        $this->assertTrue($admin->is_locked);
        $this->assertTrue($driver->is_locked);
        $this->assertEquals('Administrator', $admin->name);
        $this->assertEquals('Fahrer', $driver->name);
    }

    public function test_user_role_synced_to_pivot_on_save(): void
    {
        $user = $this->createUser('admin');

        $this->assertTrue($user->roles()->where('slug', 'admin')->exists());
    }

    // --- Multi-role ---

    public function test_user_can_have_multiple_roles(): void
    {
        $user = $this->createUser('admin');
        $user->assignRole('driver');

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isDriver());
        $this->assertEquals(2, $user->roles()->count());
    }

    // --- Pivot-based helpers ---

    public function test_is_admin_checks_pivot(): void
    {
        $user = User::create([
            'name' => 'Pivot Admin',
            'email' => 'pivot-admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');
        $user->load('roles');

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_driver_checks_pivot(): void
    {
        $user = User::create([
            'name' => 'Pivot Driver',
            'email' => 'pivot-driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('driver');
        $user->load('roles');

        $this->assertTrue($user->isDriver());
    }

    // --- Scopes ---

    public function test_scope_drivers_uses_pivot(): void
    {
        $admin = $this->createUser('admin');
        $driver = $this->createUser('driver', 'driver@test.local');

        $drivers = User::drivers()->pluck('id');

        $this->assertTrue($drivers->contains($driver->id));
        $this->assertFalse($drivers->contains($admin->id));
    }

    public function test_scope_admins_uses_pivot(): void
    {
        $admin = $this->createUser('admin');
        $driver = $this->createUser('driver', 'driver@test.local');

        $admins = User::admins()->pluck('id');

        $this->assertTrue($admins->contains($admin->id));
        $this->assertFalse($admins->contains($driver->id));
    }

    // --- Assign / remove ---

    public function test_assign_role_is_idempotent(): void
    {
        $user = $this->createUser('admin');
        $user->assignRole('admin');
        $user->assignRole('admin');

        $this->assertEquals(1, $user->roles()->where('slug', 'admin')->count());
    }

    public function test_remove_role(): void
    {
        $user = $this->createUser('admin');
        $user->assignRole('driver');

        $this->assertTrue($user->hasRole('driver'));

        $user->removeRole('driver');
        $user->load('roles');

        $this->assertFalse($user->hasRole('driver'));
    }

    // --- Permissions through roles ---

    public function test_has_permission_through_role(): void
    {
        $permission = Permission::create([
            'slug' => 'customers.view',
            'name' => 'View Customers',
            'group' => 'customers',
        ]);

        $adminRole = Role::where('slug', 'admin')->first();
        $adminRole->permissions()->attach($permission);

        $user = $this->createUser('admin');

        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertFalse($user->hasPermission('customers.delete'));
    }

    // --- Locked role properties ---

    public function test_locked_roles_have_correct_properties(): void
    {
        $this->assertEquals(2, Role::where('is_locked', true)->count());

        $slugs = Role::where('is_locked', true)->pluck('slug')->sort()->values();
        $this->assertEquals(['admin', 'driver'], $slugs->toArray());
    }

    // --- Middleware integration ---

    public function test_ensure_admin_middleware_works_with_pivot(): void
    {
        $user = $this->createUser('admin');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_ensure_admin_redirects_non_admin(): void
    {
        $user = $this->createUser('driver', 'driver@test.local');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_multi_role_user_accesses_admin_routes(): void
    {
        $user = $this->createUser('admin');
        $user->assignRole('driver');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    // --- Route binding ---

    public function test_route_bind_driver_resolves_pivot_driver(): void
    {
        $driver = $this->createUser('driver', 'driver@test.local');
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('admin.drivers.edit', $driver));
        $response->assertOk();
    }

    public function test_route_bind_driver_rejects_non_driver(): void
    {
        $admin = $this->createUser('admin');
        $admin2 = $this->createUser('admin', 'admin2@test.local');

        $response = $this->actingAs($admin)->get(route('admin.drivers.edit', $admin2));
        $response->assertNotFound();
    }

    // --- PermissionRegistry ---

    public function test_permission_registry_register_and_resolve(): void
    {
        $registry = app(PermissionRegistry::class);

        $registry->registerPermission('test.view', 'View Tests', 'test', 'example-module');

        $entry = $registry->resolve('test.view');
        $this->assertNotNull($entry);
        $this->assertEquals('test.view', $entry['slug']);
        $this->assertEquals('View Tests', $entry['label']);
        $this->assertEquals('test', $entry['group']);
        $this->assertEquals('example-module', $entry['module']);
    }

    public function test_permission_registry_get_by_group(): void
    {
        $registry = app(PermissionRegistry::class);

        $registry->registerPermission('orders.view', 'View Orders', 'orders');
        $registry->registerPermission('orders.edit', 'Edit Orders', 'orders');
        $registry->registerPermission('stock.view', 'View Stock', 'stock');

        $orderPerms = $registry->getByGroup('orders');

        $this->assertCount(2, $orderPerms);
        $this->assertArrayHasKey('orders.view', $orderPerms);
        $this->assertArrayHasKey('orders.edit', $orderPerms);
    }

    public function test_permission_registry_get_by_module(): void
    {
        $registry = app(PermissionRegistry::class);

        $registry->registerPermission('inv.view', 'View Inventory', 'inventory', 'warehouse');
        $registry->registerPermission('inv.edit', 'Edit Inventory', 'inventory', 'warehouse');
        $registry->registerPermission('acc.view', 'View Accounting', 'accounting', 'accounting');

        $warehousePerms = $registry->getByModule('warehouse');

        $this->assertCount(2, $warehousePerms);
        $this->assertArrayHasKey('inv.view', $warehousePerms);
    }

    public function test_permission_registry_remove_by_module(): void
    {
        $registry = app(PermissionRegistry::class);

        $registry->registerPermission('mod.a', 'A', 'g', 'removable');
        $registry->registerPermission('mod.b', 'B', 'g', 'removable');
        $registry->registerPermission('mod.c', 'C', 'g', 'keeper');

        $registry->removeByModule('removable');

        $this->assertFalse($registry->has('mod.a'));
        $this->assertFalse($registry->has('mod.b'));
        $this->assertTrue($registry->has('mod.c'));
    }

    // --- RoleTemplateRegistry ---

    public function test_role_template_registry_register_and_resolve(): void
    {
        $registry = app(RoleTemplateRegistry::class);

        $registry->registerTemplate(
            'warehouse-worker',
            'Lagermitarbeiter',
            'Zugriff auf Lager und Job-Einsicht',
            ['inventory.view', 'inventory.edit', 'jobs.view'],
            'warehouse'
        );

        $template = $registry->resolve('warehouse-worker');
        $this->assertNotNull($template);
        $this->assertEquals('Lagermitarbeiter', $template['name']);
        $this->assertCount(3, $template['permissions']);
        $this->assertEquals('warehouse', $template['module']);
    }

    public function test_role_template_registry_remove_by_module(): void
    {
        $registry = app(RoleTemplateRegistry::class);

        $registry->registerTemplate('t1', 'T1', 'desc', ['a'], 'mod-x');
        $registry->registerTemplate('t2', 'T2', 'desc', ['b'], 'mod-y');

        $registry->removeByModule('mod-x');

        $this->assertFalse($registry->has('t1'));
        $this->assertTrue($registry->has('t2'));
    }

    // --- Gate registration ---

    public function test_gate_defined_for_registered_permission(): void
    {
        $registry = app(PermissionRegistry::class);
        $registry->registerPermission('gate.test', 'Gate Test', 'test');

        Gate::define('gate.test', fn (User $user) => $user->hasPermission('gate.test'));

        $permission = Permission::create(['slug' => 'gate.test', 'name' => 'Gate Test', 'group' => 'test']);
        $role = Role::create(['slug' => 'tester', 'name' => 'Tester']);
        $role->permissions()->attach($permission);

        $user = User::create([
            'name' => 'Gate User',
            'email' => 'gate@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        $this->assertTrue(Gate::forUser($user)->allows('gate.test'));
    }

    public function test_gate_denies_user_without_permission(): void
    {
        Gate::define('secret.access', fn (User $user) => $user->hasPermission('secret.access'));

        $user = $this->createUser('driver', 'no-perm@test.local');

        $this->assertFalse(Gate::forUser($user)->allows('secret.access'));
    }

    public function test_admin_bypasses_all_gates(): void
    {
        Gate::define('anything.at.all', fn (User $user) => $user->hasPermission('anything.at.all'));

        $admin = $this->createUser('admin');

        $this->assertTrue(Gate::forUser($admin)->allows('anything.at.all'));
    }

    // --- Per-request permission cache ---

    public function test_permission_cache_avoids_repeated_queries(): void
    {
        $permission = Permission::create(['slug' => 'cache.test', 'name' => 'Cache Test', 'group' => 'cache']);
        $role = Role::create(['slug' => 'cached-role', 'name' => 'Cached']);
        $role->permissions()->attach($permission);

        $user = User::create([
            'name' => 'Cache User',
            'email' => 'cache@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        $user->flushPermissionCache();
        $loaded = $user->loadPermissions();
        $this->assertContains('cache.test', $loaded);

        $this->assertTrue($user->hasPermission('cache.test'));
        $this->assertFalse($user->hasPermission('nonexistent'));
    }

    public function test_cache_flushed_on_role_change(): void
    {
        $permission = Permission::create(['slug' => 'flush.test', 'name' => 'Flush Test', 'group' => 'flush']);
        $role = Role::create(['slug' => 'flush-role', 'name' => 'Flushed']);
        $role->permissions()->attach($permission);

        $user = User::create([
            'name' => 'Flush User',
            'email' => 'flush@test.local',
            'password' => Hash::make('password'),
        ]);

        $this->assertFalse($user->hasPermission('flush.test'));

        $user->assignRole($role);
        $this->assertTrue($user->hasPermission('flush.test'));

        $user->removeRole($role);
        $this->assertFalse($user->hasPermission('flush.test'));
    }
}
