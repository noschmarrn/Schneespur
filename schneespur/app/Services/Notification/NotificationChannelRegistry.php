<?php

namespace App\Services\Notification;

use App\Models\Job;
use App\Services\Extension\ExtensionRegistry;
use App\Services\Extension\FilterRegistry;
use Illuminate\Contracts\Container\Container;
use App\Services\Diagnostic\DiagnosticManager;
use Illuminate\Support\Facades\Log;

class NotificationChannelRegistry extends ExtensionRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly FilterRegistry $filterRegistry,
    ) {}

    /**
     * @param class-string<NotificationChannelInterface> $channelClass
     */
    public function register(string $slug, mixed $channelClass): void
    {
        parent::register($slug, $channelClass);
    }

    /**
     * @return array<int, array{slug: string, status: string, error: string|null}>
     */
    public function dispatch(Job $job, string $type, array $context): array
    {
        $channels = $this->enabledChannels();
        $channels = $this->filterRegistry->apply('schneespur.job.notification.channels', $channels, $job);

        $results = [];
        foreach ($channels as $slug => $channel) {
            try {
                $channel->send($job, $type, $context);
                $results[] = ['slug' => $slug, 'status' => 'sent', 'error' => null];
            } catch (\Throwable $e) {
                Log::warning("NotificationChannelRegistry: channel '{$slug}' failed: {$e->getMessage()}");
                $results[] = ['slug' => $slug, 'status' => 'failed', 'error' => $e->getMessage()];

                try {
                    app(DiagnosticManager::class)->report('notification_dispatch_failed', [
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ], [
                        'slug' => $slug,
                        'job_id' => $job->id,
                        'type' => $type,
                        'source' => 'NotificationChannelRegistry',
                    ]);
                } catch (\Throwable) {
                    // Never let diagnostic reporting break the original flow
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string, NotificationChannelInterface>
     */
    public function enabledChannels(): array
    {
        $enabled = [];
        foreach ($this->items as $slug => $channelClass) {
            $channel = $this->container->make($channelClass);
            if ($channel->isEnabled()) {
                $enabled[$slug] = $channel;
            }
        }

        return $enabled;
    }
}
