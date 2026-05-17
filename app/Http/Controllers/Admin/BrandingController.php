<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.branding', [
            'logoPath' => Setting::get('company_logo_path'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'company_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
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
        $path = Setting::get('company_logo_path');

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Setting::set('company_logo_path', '');

        return redirect()->route('admin.settings.branding')
            ->with('success', __('ui.saved'));
    }
}
