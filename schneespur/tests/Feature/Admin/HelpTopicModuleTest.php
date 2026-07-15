<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Extension\HelpTopicRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelpTopicModuleTest extends TestCase
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

    private function createAdmin(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => Hash::make('password')]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    public function test_module_topic_appears_in_index_and_is_reachable(): void
    {
        app(HelpTopicRegistry::class)->registerTopic(
            'lager',
            'Lager Test Topic',
            'admin.help.topics.overview', // reuse an existing view so show() renders
            'Stock help text',
        );

        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('admin.help.index'))
            ->assertOk()->assertSee('Lager Test Topic');

        $this->actingAs($admin)->get(route('admin.help.show', 'lager'))
            ->assertOk();
    }

    public function test_core_topic_still_renders(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('admin.help.show', 'jobs'))
            ->assertOk()->assertSee(__('help.topic_jobs'));
    }

    public function test_unknown_topic_404s(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('admin.help.show', 'does-not-exist'))
            ->assertNotFound();
    }
}
