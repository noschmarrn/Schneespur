<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Scheduler\ScheduledTaskRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminCronTaskController extends Controller
{
    public function __construct(
        private readonly ScheduledTaskRegistry $registry,
    ) {}

    public function index()
    {
        Gate::authorize('crontasks.view');

        $tasks = $this->registry->allWithStatus();

        return view('admin.crontasks.index', [
            'tasks' => $tasks,
        ]);
    }

    public function toggle(Request $request, string $slug)
    {
        Gate::authorize('crontasks.manage');

        $task = $this->registry->resolve($slug);

        if (! $task) {
            abort(404);
        }

        if ($task->source() === 'core') {
            abort(403);
        }

        $key = "scheduled_task.{$slug}.enabled";
        $current = Setting::get($key, '1');
        Setting::set($key, $current === '1' ? '0' : '1');

        return redirect()->route('admin.crontasks.index');
    }
}
