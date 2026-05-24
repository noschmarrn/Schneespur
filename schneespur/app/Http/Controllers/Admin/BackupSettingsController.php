<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Backup\BackupTargetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BackupSettingsController extends Controller
{
    public function edit(BackupTargetRegistry $registry): View
    {
        Gate::authorize('settings.view');

        return view('admin.settings.backup', [
            'targets' => $registry->availableTargets(),
            'activeTarget' => $registry->activeSlug(),
        ]);
    }

    public function update(Request $request, BackupTargetRegistry $registry): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $targetSlugs = array_keys($registry->availableTargets());

        $validated = $request->validate([
            'backup_target' => ['required', 'string', 'in:'.implode(',', $targetSlugs)],
        ]);

        Setting::set('backup_target', $validated['backup_target']);

        return redirect()->route('admin.settings.backup')
            ->with('success', __('backup.settings_saved'));
    }
}
