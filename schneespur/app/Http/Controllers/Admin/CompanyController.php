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
            'company_state' => Setting::get('company_state', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'company_lat' => Setting::get('company_lat'),
            'company_lon' => Setting::get('company_lon'),
            'dpo_contact' => Setting::get('dpo_contact', ''),
            'dpo_email' => Setting::get('dpo_email', ''),
            'season_from' => Setting::get('season_from', '11-01'),
            'season_to' => Setting::get('season_to', '03-31'),
            'alert_overdue_hours' => Setting::get('alert_overdue_hours', 4),
            'report_show_amounts' => (bool) Setting::get('report_show_amounts', false),
            'billing_increment_minutes' => app(\App\Services\Pricing\JobPricing::class)->incrementMinutes(),
            'default_locale' => Setting::get('default_locale', 'de'),
            'locales' => app(LocaleRegistry::class)->labels(),
            'display_timezone' => \App\Support\Timezones::resolve(),
            'timezones' => \App\Support\Timezones::grouped(),
            'app_brand' => brand_slug(),
            'brands' => brand_options(),
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
            'company_state' => ['nullable', 'string', 'max:64'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'dpo_contact' => ['nullable', 'string', 'max:255'],
            'dpo_email' => ['nullable', 'email', 'max:255'],
            'season_from' => ['required', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'season_to' => ['required', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'alert_overdue_hours' => ['required', 'integer', 'min:1'],
            // 'sometimes': ein älteres Formular oder ein API-Client, der das
            // Feld nicht schickt, behält den gespeicherten Takt.
            'report_show_amounts' => ['sometimes', 'boolean'],
            // Markiert, daß das Formular den Schalter überhaupt enthielt: ein
            // nicht angehaktes Kästchen schickt nichts, und ohne diese Marke
            // wäre "ausgeschaltet" von "Feld fehlt" nicht zu unterscheiden.
            'report_show_amounts_present' => ['sometimes', 'boolean'],
            'billing_increment_minutes' => ['sometimes', 'integer', Rule::in(\App\Services\Pricing\JobPricing::INCREMENTS)],
            'default_locale' => ['required', 'string', Rule::in(app(LocaleRegistry::class)->codes())],
            // Not Rule::in() over 400-odd identifiers on every request — and a
            // rejected value matters: an invalid zone throws inside
            // Formatter::formatMoment(), which is to say on every page with a date.
            'display_timezone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! \App\Support\Timezones::isValid((string) $value)) {
                    $fail(__('settings.timezone_invalid'));
                }
            }],
            // Optional so older forms / API clients that never post it keep the
            // brand they were installed with instead of silently resetting it.
            'app_brand' => ['nullable', 'string', Rule::in(array_keys(brand_options()))],
        ]);

        Setting::set('company_name', $validated['company_name']);
        Setting::set('company_street', $validated['company_street'] ?? '');
        Setting::set('company_zip', $validated['company_zip'] ?? '');
        Setting::set('company_city', $validated['company_city'] ?? '');
        Setting::set('company_state', $validated['company_state'] ?? '');
        Setting::set('company_phone', $validated['company_phone'] ?? '');
        Setting::set('company_email', $validated['company_email'] ?? '');
        Setting::set('dpo_contact', $validated['dpo_contact'] ?? '');
        Setting::set('dpo_email', $validated['dpo_email'] ?? '');
        Setting::set('season_from', $validated['season_from']);
        Setting::set('season_to', $validated['season_to']);
        Setting::set('alert_overdue_hours', $validated['alert_overdue_hours'], 'int');
        if (array_key_exists('billing_increment_minutes', $validated)) {
            Setting::set('billing_increment_minutes', (int) $validated['billing_increment_minutes'], 'int');
        }
        if ($request->has('report_show_amounts') || $request->has('report_show_amounts_present')) {
            Setting::set('report_show_amounts', $request->boolean('report_show_amounts'), 'bool');
        }
        Setting::set('default_locale', $validated['default_locale']);
        Setting::set('display_timezone', $validated['display_timezone']);

        if (! empty($validated['app_brand'])) {
            Setting::set('app_brand', $validated['app_brand']);
        }

        $street = $validated['company_street'] ?? '';
        $zip = $validated['company_zip'] ?? '';
        $city = $validated['company_city'] ?? '';
        $state = $validated['company_state'] ?? '';

        if ($street !== '' && $zip !== '' && $city !== '') {
            $result = $geocoding->resolve($street, $zip, $city, $state);

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
