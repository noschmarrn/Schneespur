<?php

namespace Tests\Feature\Extension;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Extension\JobTypeRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JobTypePickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $lock = storage_path('app/installed.lock');
        if (! file_exists($lock)) {
            @mkdir(dirname($lock), 0755, true);
            file_put_contents($lock, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_admin_manual_job_picker_includes_module_type(): void
    {
        app(JobTypeRegistry::class)->registerType('maehen', 'job.type_maehen');

        $admin = User::create(['name' => 'A', 'email' => 'adm@test.local', 'password' => Hash::make('password')]);
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin->fresh())
            ->get(route('admin.jobs.manual.create'))
            ->assertOk()
            ->assertSee('maehen');
    }
}
