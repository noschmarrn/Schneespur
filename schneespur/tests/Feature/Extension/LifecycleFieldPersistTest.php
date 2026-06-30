<?php

namespace Tests\Feature\Extension;

use App\Enums\LifecyclePoint;
use App\Models\Customer;
use App\Models\User;
use App\Services\Extension\LifecycleFieldRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LifecycleFieldPersistTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function user(): User
    {
        return User::create(['name' => 'U', 'email' => 'p@test.local', 'password' => Hash::make('x')]);
    }

    public function test_handler_persists_and_receives_entity_and_validated(): void
    {
        $registry = new LifecycleFieldRegistry();
        $seen = [];

        $registry->registerField(LifecyclePoint::JobEnd, 'demo.ok', [
            'persist' => function ($entity, array $validated, $user) use (&$seen) {
                $seen = ['id' => $entity->id, 'value' => $validated['demo_field'], 'user' => $user->id];
            },
        ]);

        $user = $this->user();
        $entity = Customer::create(['name' => 'Entity']);

        DB::transaction(fn () => $registry->persist(LifecyclePoint::JobEnd, $entity, ['demo_field' => 42], $user));

        $this->assertSame(['id' => $entity->id, 'value' => 42, 'user' => $user->id], $seen);
    }

    public function test_throwing_handler_is_isolated_and_does_not_break_outer_transaction(): void
    {
        Log::spy();

        $registry = new LifecycleFieldRegistry();
        $registry->registerField(LifecyclePoint::JobEnd, 'demo.bad', [
            'persist' => function () {
                throw new \RuntimeException('module bug');
            },
        ]);

        $user = $this->user();

        // The outer transaction performs a real write, then the failing handler runs.
        DB::transaction(function () use ($registry, $user) {
            $entity = Customer::create(['name' => 'Survivor']);
            $registry->persist(LifecyclePoint::JobEnd, $entity, [], $user);
        });

        // Core write survived; the failing handler did not abort it.
        $this->assertDatabaseHas('customers', ['name' => 'Survivor']);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }
}
