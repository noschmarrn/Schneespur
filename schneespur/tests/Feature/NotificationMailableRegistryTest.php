<?php

namespace Tests\Feature;

use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\MailableRegistry;
use App\Services\Notification\NotificationChannelInterface;
use App\Services\Notification\NotificationChannelRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\LegacyTestChannel;
use Tests\TestCase;

class NotificationMailableRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function job(): Job
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Test Customer']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => '2026-08-06 08:00:00',
            'ended_at' => '2026-08-06 10:00:00',
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => '2026-08-06 08:00:00',
            'ended_at' => '2026-08-06 10:00:00',
        ]);
    }

    private function context(): array
    {
        return [
            'recipients' => ['kunde@test.local'],
            'weather_available' => false,
            'pdf_content' => null,
            'pdf_filename' => '',
            'is_weather_update' => false,
        ];
    }

    public function test_both_core_types_still_send_the_completion_mail(): void
    {
        Mail::fake();

        $channel = app(EmailNotificationChannel::class);
        $job = $this->job();

        $channel->send($job, 'job_completed', $this->context());
        $channel->send($job, 'job_completed_updated', $this->context());

        // JobCompletedMail implements ShouldQueue (existing behaviour,
        // unrelated to this task), so it is queued rather than sent
        // synchronously — matches NotificationChannelRegistryTest's
        // assertQueued() for the same mailable.
        Mail::assertQueued(JobCompletedMail::class, 2);
    }

    /**
     * The bug this whole task exists for: a module calling the documented
     * registry with its own type used to send the customer an "auftrag
     * abgeschlossen" mail for a job that had not started.
     */
    public function test_an_unknown_type_sends_nothing(): void
    {
        Mail::fake();

        app(EmailNotificationChannel::class)
            ->send($this->job(), 'driver_en_route', $this->context());

        Mail::assertNothingSent();
    }

    public function test_a_module_can_register_its_own_mailable(): void
    {
        Mail::fake();

        app(MailableRegistry::class)->registerType(
            'driver_en_route',
            fn (Job $job, array $context) => new TestEnRouteMail($job),
        );

        app(EmailNotificationChannel::class)
            ->send($this->job(), 'driver_en_route', $this->context());

        Mail::assertSent(TestEnRouteMail::class, 1);
    }

    public function test_the_channel_reports_skipped_rather_than_sent(): void
    {
        Mail::fake();

        $results = app(NotificationChannelRegistry::class)
            ->dispatch($this->job(), 'driver_en_route', $this->context());

        $email = collect($results)->firstWhere('slug', 'email');

        $this->assertSame('skipped', $email['status']);
    }

    /**
     * The telegram module implements NotificationChannelInterface and not the
     * new one. It must keep being called exactly as before — a required
     * method on the published interface would be a fatal on its next update.
     */
    public function test_a_channel_without_the_new_interface_is_still_called(): void
    {
        LegacyTestChannel::$called = false;

        $registry = app(NotificationChannelRegistry::class);
        // A class name, not an instance: enabledChannels() resolves each entry
        // through the container ($this->container->make($channelClass)).
        $registry->register('legacy', LegacyTestChannel::class);

        $results = $registry->dispatch($this->job(), 'driver_en_route', $this->context());

        $legacyResult = collect($results)->firstWhere('slug', 'legacy');

        $this->assertSame('sent', $legacyResult['status']);
        $this->assertTrue(LegacyTestChannel::$called);
    }
}

class TestEnRouteMail extends Mailable
{
    public function __construct(public Job $job) {}

    public function build(): self
    {
        return $this->html('en route');
    }
}
