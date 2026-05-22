<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Extension\FilterRegistry;
use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\NotificationChannelInterface;
use App\Services\Notification\NotificationChannelRegistry;
use App\Services\NotificationLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationChannelRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private NotificationChannelRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new NotificationChannelRegistry(
            $this->app,
            $this->app->make(FilterRegistry::class),
        );
    }

    public function test_dispatch_with_no_channels_returns_empty_array(): void
    {
        $job = $this->createTestJob();

        $results = $this->registry->dispatch($job, 'job_completed', ['recipients' => ['test@example.com']]);

        $this->assertSame([], $results);
    }

    public function test_dispatch_calls_enabled_channels(): void
    {
        $called = false;
        $channel = $this->makeChannel(
            slug: 'test',
            name: 'Test',
            enabled: true,
            onSend: function () use (&$called) { $called = true; },
        );

        $this->app->bind('test-channel', fn () => $channel);
        $this->registry->register('test', 'test-channel');

        $job = $this->createTestJob();
        $results = $this->registry->dispatch($job, 'job_completed', ['recipients' => []]);

        $this->assertTrue($called);
        $this->assertCount(1, $results);
        $this->assertSame('sent', $results[0]['status']);
        $this->assertSame('test', $results[0]['slug']);
    }

    public function test_dispatch_skips_disabled_channels(): void
    {
        $channel = $this->makeChannel(
            slug: 'disabled',
            name: 'Disabled',
            enabled: false,
            onSend: function () { throw new \RuntimeException('should not be called'); },
        );

        $this->app->bind('disabled-channel', fn () => $channel);
        $this->registry->register('disabled', 'disabled-channel');

        $job = $this->createTestJob();
        $results = $this->registry->dispatch($job, 'job_completed', []);

        $this->assertSame([], $results);
    }

    public function test_dispatch_isolates_channel_failures(): void
    {
        $sentSlugs = [];

        $failingChannel = $this->makeChannel(
            slug: 'failing',
            name: 'Failing',
            enabled: true,
            onSend: function () { throw new \RuntimeException('channel error'); },
        );

        $goodChannel = $this->makeChannel(
            slug: 'good',
            name: 'Good',
            enabled: true,
            onSend: function () use (&$sentSlugs) { $sentSlugs[] = 'good'; },
        );

        $this->app->bind('failing-ch', fn () => $failingChannel);
        $this->app->bind('good-ch', fn () => $goodChannel);
        $this->registry->register('failing', 'failing-ch');
        $this->registry->register('good', 'good-ch');

        $job = $this->createTestJob();
        $results = $this->registry->dispatch($job, 'job_completed', []);

        $this->assertCount(2, $results);
        $this->assertSame('failed', $results[0]['status']);
        $this->assertSame('channel error', $results[0]['error']);
        $this->assertSame('sent', $results[1]['status']);
        $this->assertContains('good', $sentSlugs);
    }

    public function test_dispatch_applies_channels_filter_hook(): void
    {
        $filterRegistry = $this->app->make(FilterRegistry::class);

        $filterRegistry->register('schneespur.job.notification.channels', function (array $channels) {
            unset($channels['blocked']);
            return $channels;
        });

        $blockedChannel = $this->makeChannel(
            slug: 'blocked',
            name: 'Blocked',
            enabled: true,
            onSend: function () { throw new \RuntimeException('should not run'); },
        );

        $this->app->bind('blocked-ch', fn () => $blockedChannel);
        $this->registry->register('blocked', 'blocked-ch');

        $job = $this->createTestJob();
        $results = $this->registry->dispatch($job, 'job_completed', []);

        $this->assertSame([], $results);
    }

    public function test_email_channel_sends_mail_and_logs(): void
    {
        Mail::fake();

        $job = $this->createTestJob();
        $channel = $this->app->make(EmailNotificationChannel::class);

        $channel->send($job, 'job_completed', [
            'recipients' => ['customer@example.com'],
            'weather_available' => true,
            'pdf_content' => null,
            'pdf_filename' => '',
            'is_weather_update' => false,
            'customer_object_id' => null,
            'customer_object_name' => null,
        ]);

        Mail::assertQueued(\App\Mail\JobCompletedMail::class, function ($mail) {
            return $mail->hasTo('customer@example.com');
        });

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'email',
            'type' => 'job_completed',
            'recipient' => 'customer@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_notification_log_service_records_custom_channel(): void
    {
        $job = $this->createTestJob();
        $logService = $this->app->make(NotificationLogService::class);

        $log = $logService->logSent($job, 'job_completed', 'user@example.com', [], 'telegram');

        $this->assertSame('telegram', $log->channel);
        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'telegram',
            'status' => 'sent',
        ]);
    }

    public function test_notification_log_service_defaults_to_email_channel(): void
    {
        $job = $this->createTestJob();
        $logService = $this->app->make(NotificationLogService::class);

        $log = $logService->logSent($job, 'job_completed', 'user@example.com');

        $this->assertSame('email', $log->channel);
    }

    public function test_registry_resolves_from_app_container_as_singleton(): void
    {
        $registry = $this->app->make(NotificationChannelRegistry::class);

        $this->assertInstanceOf(NotificationChannelRegistry::class, $registry);
        $this->assertTrue($registry->has('email'));
        $this->assertSame($registry, $this->app->make(NotificationChannelRegistry::class));
    }

    public function test_enabled_channels_returns_only_enabled(): void
    {
        $enabled = $this->makeChannel(slug: 'on', name: 'On', enabled: true);
        $disabled = $this->makeChannel(slug: 'off', name: 'Off', enabled: false);

        $this->app->bind('ch-on', fn () => $enabled);
        $this->app->bind('ch-off', fn () => $disabled);
        $this->registry->register('on', 'ch-on');
        $this->registry->register('off', 'ch-off');

        $channels = $this->registry->enabledChannels();

        $this->assertArrayHasKey('on', $channels);
        $this->assertArrayNotHasKey('off', $channels);
    }

    public function test_example_module_registers_dummy_log_channel(): void
    {
        $this->bootExampleModule();

        $registry = $this->app->make(NotificationChannelRegistry::class);

        $this->assertTrue($registry->has('dummy-log'));
    }

    public function test_dummy_log_channel_writes_to_log(): void
    {
        $this->bootExampleModule();

        $registry = $this->app->make(NotificationChannelRegistry::class);
        $channel = $this->app->make($registry->resolve('dummy-log'));

        $job = $this->createTestJob();

        Log::spy();

        $channel->send($job, 'job_completed', ['recipients' => ['a@b.com', 'c@d.com']]);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'DummyLogChannel: notification dispatched'))
            ->once();
    }

    private function createTestJob(): Job
    {
        $user = User::create([
            'name' => 'Test Driver',
            'email' => 'driver-' . uniqid() . '@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'auto_notify_email' => true,
        ]);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);

        $shift = WorkShift::create([
            'user_id' => $user->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
        ]);
    }

    private function makeChannel(string $slug, string $name, bool $enabled, ?\Closure $onSend = null): NotificationChannelInterface
    {
        return new class($slug, $name, $enabled, $onSend) implements NotificationChannelInterface {
            public function __construct(
                private string $channelSlug,
                private string $channelName,
                private bool $channelEnabled,
                private ?\Closure $sendCallback = null,
            ) {}

            public function send(Job $job, string $type, array $context): void
            {
                if ($this->sendCallback) {
                    ($this->sendCallback)($job, $type, $context);
                }
            }

            public function name(): string { return $this->channelName; }
            public function slug(): string { return $this->channelSlug; }
            public function isEnabled(): bool { return $this->channelEnabled; }
        };
    }

    private function bootExampleModule(): void
    {
        $modulePath = base_path('modules/example/src');
        spl_autoload_register(function (string $class) use ($modulePath) {
            $prefix = 'Schneespur\\Module\\Example\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $modulePath . '/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });

        putenv('EXAMPLE_MODULE_ENABLED=true');
        $_ENV['EXAMPLE_MODULE_ENABLED'] = true;

        $this->app->register(\Schneespur\Module\Example\ExampleServiceProvider::class);
    }
}
