<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Extension\LocaleRegistry;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('settings.view');

        return view('admin.settings.company', [
            'company_name' => Setting::get('company_name', ''),
            'company_street' => Setting::get('company_street', ''),
            'company_zip' => Setting::get('company_zip', ''),
            'company_city' => Setting::get('company_city', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'company_lat' => Setting::get('company_lat'),
            'company_lon' => Setting::get('company_lon'),
            'dpo_contact' => Setting::get('dpo_contact', ''),
            'dpo_email' => Setting::get('dpo_email', ''),
            'season_from' => Setting::get('season_from', '11-01'),
            'season_to' => Setting::get('season_to', '03-31'),
            'alert_overdue_hours' => Setting::get('alert_overdue_hours', 4),
            'default_locale' => Setting::get('default_locale', 'de'),
            'locales' => app(LocaleRegistry::class)->labels(),
        ]);
    }

    public function update(Request $request, GeocodingService $geocoding): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_street' => ['nullable', 'string', 'max:255'],
            'company_zip' => ['nullable', 'string', 'max:10'],
            'company_city' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'dpo_contact' => ['nullable', 'string', 'max:255'],
            'dpo_email' => ['nullable', 'email', 'max:255'],
            'season_from' => ['required', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'season_to' => ['required', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'alert_overdue_hours' => ['required', 'integer', 'min:1'],
            'default_locale' => ['required', 'string', Rule::in(app(LocaleRegistry::class)->codes())],
        ]);

        Setting::set('company_name', $validated['company_name']);
        Setting::set('company_street', $validated['company_street'] ?? '');
        Setting::set('company_zip', $validated['company_zip'] ?? '');
        Setting::set('company_city', $validated['company_city'] ?? '');
        Setting::set('company_phone', $validated['company_phone'] ?? '');
        Setting::set('company_email', $validated['company_email'] ?? '');
        Setting::set('dpo_contact', $validated['dpo_contact'] ?? '');
        Setting::set('dpo_email', $validated['dpo_email'] ?? '');
        Setting::set('season_from', $validated['season_from']);
        Setting::set('season_to', $validated['season_to']);
        Setting::set('alert_overdue_hours', $validated['alert_overdue_hours'], 'int');
        Setting::set('default_locale', $validated['default_locale']);

        $street = $validated['company_street'] ?? '';
        $zip = $validated['company_zip'] ?? '';
        $city = $validated['company_city'] ?? '';

        if ($street !== '' && $zip !== '' && $city !== '') {
            $oldStreet = Setting::get('company_street', '');
            $oldZip = Setting::get('company_zip', '');
            $oldCity = Setting::get('company_city', '');

            $result = $geocoding->resolve($street, $zip, $city);

            if ($result) {
                Setting::set('company_lat', (string) $result['lat']);
                Setting::set('company_lon', (string) $result['lon']);

                return redirect()->route('admin.settings.company')
                    ->with('success', __('settings.company_geocode_success').' ('.$result['lat'].', '.$result['lon'].')');
            }

            return redirect()->route('admin.settings.company')
                ->with('warning', __('settings.company_geocode_fail'));
        }

        return redirect()->route('admin.settings.company')
            ->with('success', __('ui.saved'));
    }
}
