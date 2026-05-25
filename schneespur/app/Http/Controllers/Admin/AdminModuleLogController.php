<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModLog;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminModuleLogController extends Controller
{
    public function index(Request $request, string $slug): View
    {
        Gate::authorize('settings.view');

        $module = Module::where('slug', $slug)->firstOrFail();

        $currentLevel = $request->query('level');

        $logs = ModLog::forModule($slug)
            ->when($currentLevel, fn ($q) => $q->ofLevel($currentLevel))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.settings.modules.logs.index', [
            'module' => $module,
            'logs' => $logs,
            'currentLevel' => $currentLevel,
        ]);
    }
}
