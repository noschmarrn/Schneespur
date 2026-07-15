<?php

namespace Tests\Feature\Events;

use App\Enums\UserRole;
use App\Events\User\UserAnonymized;
use App\Models\User;
use App\Services\DriverAnonymizationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAnonymizedTest extends TestCase
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

    private function createDriver(): User
    {
        $user = User::create(['name' => 'Driver', 'email' => 'driver@test.local', 'password' => Hash::make('password')]);
        $user->role = UserRole::Driver;
        $user->save();

        return $user->fresh();
    }

    public function test_anonymize_dispatches_event_with_committed_state(): void
    {
        Event::fake([UserAnonymized::class]);

        $driver = $this->createDriver();
        app(DriverAnonymizationService::class)->anonymize($driver, 'gdpr request');

        Event::assertDispatched(UserAnonymized::class, function (UserAnonymized $e) use ($driver) {
            return $e->user->id === $driver->id
                && $e->reason === 'gdpr request'
                && $e->user->isAnonymized();
        });
    }
}
