<?php

use App\Models\Setting;
use App\Services\Format\RegionRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the format settings from the install locale.
     *
     * The web installer sets them explicitly, but the Docker/Coolify deployment
     * only ever runs `migrate` — no wizard. There this migration IS the
     * decision, so it must read APP_LOCALE instead of assuming Germany. Same
     * shape as 2026_05_10_000001_seed_app_brand_setting: existing installs are
     * untouched because it only fills a missing value.
     *
     * Why `us` and not `gb` as the non-German template: the English interface
     * is, per the operator's own numbers, predominantly installed in North
     * America, and the template is one click away from being changed in
     * Settings. This is an assumption, not a truth — it is documented here
     * (and in the spec) rather than left silent in the code.
     */
    public function up(): void
    {
        if (Setting::get('format_region') !== null) {
            return;
        }

        $code = config('app.locale') === 'de' ? 'de' : 'us';
        $preset = app(RegionRegistry::class)->get($code);

        if ($preset === null) {
            return; // registry not booted (should not happen); leave defaults
        }

        foreach ($preset->toSettings() as $key => $value) {
            Setting::set($key, $value);
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'format_region', 'currency_code', 'currency_position', 'number_format',
            'date_format', 'time_format', 'unit_distance', 'unit_temperature', 'unit_weight',
        ])->delete();
    }
};
