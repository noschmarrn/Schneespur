<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandingController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('settings.view');

        return view('admin.settings.branding', [
            'logoPath' => Setting::get('company_logo_path'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $request->validate([
            // Raster only. SVG is an active document type (script/onload XSS);
            // Laravel's `image` rule already rejects it unless `allow_svg` is
            // set, so listing svg here was dead/misleading config. If SVG logos
            // are ever wanted, sanitize on upload before re-enabling.
            'company_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        if ($request->hasFile('company_logo')) {
            $oldPath = Setting::get('company_logo_path');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('company_logo')->store('branding', 'public');
            Setting::set('company_logo_path', $path);
        }

        return redirect()->route('admin.settings.branding')
            ->with('success', __('ui.saved'));
    }

    public function deleteLogo(): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $path = Setting::get('company_logo_path');

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Setting::set('company_logo_path', '');

        return redirect()->route('admin.settings.branding')
            ->with('success', __('ui.saved'));
    }
}
