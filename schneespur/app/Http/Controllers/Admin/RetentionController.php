<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetentionController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.retention', [
            'retention_years' => Setting::get('retention_years', 3),
            'retention_auto_delete' => Setting::get('retention_auto_delete', false),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'retention_years' => ['required', 'integer', 'min:3'],
            'retention_auto_delete' => ['nullable', 'boolean'],
        ]);

        Setting::set('retention_years', $validated['retention_years'], 'int');
        Setting::set('retention_auto_delete', $request->boolean('retention_auto_delete'), 'bool');

        return redirect()->route('admin.settings.retention')
            ->with('success', __('ui.saved'));
    }
}
