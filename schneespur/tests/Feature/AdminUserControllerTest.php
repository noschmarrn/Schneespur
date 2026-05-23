<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
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

    public function test_admin_can_view_users_index(): void
    {
        $admin = $this->createAdmin();
        $other = $this->createDriver('other@test.local');

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee($other->name);
    }

    public function test_admin_can_search_users(): void
    {
        $admin = $this->createAdmin();
        $alice = User::create(['name' => 'Alice Wonderland', 'email' => 'alice@test.local', 'password' => Hash::make('password')]);
        $bob = User::create(['name' => 'Bob Builder', 'email' => 'bob@test.local', 'password' => Hash::make('password')]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Alice']));

        $response->assertOk();
        $response->assertSee('Alice Wonderland');
        $response->assertDontSee('Bob Builder');
    }

    public function test_admin_can_create_user_with_roles(): void
    {
        $admin = $this->createAdmin();
        $driverRole = Role::where('slug', 'driver')->first();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@test.local',
            'password' => 'securepass123',
            'roles' => [$driverRole->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $newUser = User::where('email', 'newuser@test.local')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('driver'));
    }

    public function test_admin_can_update_user_and_change_roles(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createDriver('target@test.local');
        $adminRole = Role::where('slug', 'admin')->first();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => 'target@test.local',
            'roles' => [$adminRole->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user->refresh()->load('roles');
        $this->assertEquals('Updated Name', $user->name);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_update_without_password_preserves_existing(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createDriver('target@test.local');
        $originalHash = $user->password;

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertEquals($originalHash, $user->fresh()->password);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createDriver('delete-me@test.local');

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNull(User::find($user->id));
    }

    public function test_cannot_delete_last_admin(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error');
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_cannot_remove_admin_role_from_last_admin(): void
    {
        $admin = $this->createAdmin();
        $driverRole = Role::where('slug', 'driver')->first();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [$driverRole->id],
        ]);

        $response->assertRedirect(route('admin.users.edit', $admin));
        $response->assertSessionHas('error');
        $admin->refresh()->load('roles');
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_non_admin_without_permission_gets_403(): void
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($driver)->get(route('admin.users.index'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_store_validates_unique_email(): void
    {
        $admin = $this->createAdmin();
        $existing = $this->createDriver('existing@test.local');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Duplicate',
            'email' => 'existing@test.local',
            'password' => 'securepass123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
