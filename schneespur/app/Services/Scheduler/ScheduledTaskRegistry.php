<?php

namespace App\Services\Scheduler;

use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

class ScheduledTaskRegistry extends ExtensionRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<ScheduledTaskInterface> $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(string $slug): ?ScheduledTaskInterface
    {
        if (! $this->has($slug)) {
            return null;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return ScheduledTaskInterface[]
     */
    public function enabledTasks(): array
    {
        $enabled = [];
        foreach ($this->all() as $slug => $class) {
            $task = $this->container->make($class);
            if ($task->isEnabled()) {
                $enabled[$slug] = $task;
            }
        }

        return $enabled;
    }

    public function recordRun(string $slug, string $status, ?string $error, int $durationMs): void
    {
        DB::table('scheduled_task_runs')->insert([
            'slug' => $slug,
            'status' => $status,
            'error_message' => $error,
            'duration_ms' => $durationMs,
            'ran_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function lastRun(string $slug): ?object
    {
        return DB::table('scheduled_task_runs')
            ->where('slug', $slug)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, array{task: ScheduledTaskInterface, last_run: ?object}>
     */
    public function allWithStatus(): array
    {
        $result = [];
        foreach ($this->all() as $slug => $class) {
            $task = $this->container->make($class);
            $result[$slug] = [
                'task' => $task,
                'last_run' => $this->lastRun($slug),
            ];
        }

        return $result;
    }
}
