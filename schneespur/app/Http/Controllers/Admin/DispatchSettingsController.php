<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Extension\DispatchStrategyRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DispatchSettingsController extends Controller
{
    public function edit(DispatchStrategyRegistry $registry): View
    {
        Gate::authorize('settings.view');

        return view('admin.settings.dispatch', [
            'strategies' => $registry->availableStrategies(),
            'activeStrategy' => $registry->activeSlug(),
        ]);
    }

    public function update(Request $request, DispatchStrategyRegistry $registry): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $strategySlugs = array_keys($registry->availableStrategies());

        $validated = $request->validate([
            'dispatch_strategy' => ['required', 'string', 'in:'.implode(',', $strategySlugs)],
        ]);

        Setting::set('dispatch_strategy', $validated['dispatch_strategy']);

        return redirect()->route('admin.settings.dispatch')
            ->with('success', __('dispatch.settings_saved'));
    }
}
