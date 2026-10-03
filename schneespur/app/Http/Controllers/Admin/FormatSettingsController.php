<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Format\Formatter;
use App\Services\Format\RegionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormatSettingsController extends Controller
{
    private const KEYS = [
        'currency_code',
        'currency_position',
        'number_format',
        'date_format',
        'time_format',
        'unit_distance',
        'unit_temperature',
        'unit_weight',
        'address_format',
        'csv_delimiter',
        'week_starts_on',
    ];

    public function edit(RegionRegistry $regions): View
    {
        Gate::authorize('settings.view');

        return view('admin.settings.format', [
            'regions' => $regions->labels(),
            'current' => collect(self::KEYS)
                ->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])
                ->all(),
            'activeRegion' => Setting::get('format_region'),
        ]);
    }

    public function update(Request $request, RegionRegistry $regions): RedirectResponse
    {
        Gate::authorize('settings.edit');

        // Two shapes on one endpoint: "apply this template" and "save these
        // fields". Splitting them into two routes would mean two forms on one
        // page and a second CSRF dance for no gain.
        if ($request->filled('apply_region')) {
            $validated = $request->validate([
                'apply_region' => ['required', 'string', Rule::in(array_keys($regions->labels()))],
            ]);

            foreach ($regions->get($validated['apply_region'])->toSettings() as $key => $value) {
                Setting::set($key, $value);
            }

            return $this->done();
        }

        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'currency_position' => ['required', Rule::in(['before', 'after'])],
            'number_format' => ['required', Rule::in(['dot_comma', 'comma_dot', 'apostrophe_dot'])],
            'date_format' => ['required', Rule::in(['dmy_dot', 'dmy_slash', 'mdy_slash', 'iso'])],
            'time_format' => ['required', Rule::in(['24h', '12h'])],
            'unit_distance' => ['required', Rule::in(['metric', 'imperial'])],
            'unit_temperature' => ['required', Rule::in(['celsius', 'fahrenheit'])],
            'unit_weight' => ['required', Rule::in(['metric', 'imperial'])],
            'address_format' => ['required', Rule::in(['street_zip_city', 'street_city_state_zip', 'street_city_zip'])],
            'csv_delimiter' => ['required', Rule::in([';', ','])],
            'week_starts_on' => ['required', Rule::in(['1', '7'])],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        // The region no longer describes the state once a field was changed by
        // hand. Keep the code for the picker, but the view marks it as edited.
        return $this->done();
    }

    private function done(): RedirectResponse
    {
        // The Formatter is a singleton holding the OLD values; drop it so the
        // very next request (and anything later in this one) reads the new ones.
        app()->forgetInstance(Formatter::class);

        return redirect()->route('admin.settings.format')
            ->with('success', __('format.settings_saved'));
    }
}
