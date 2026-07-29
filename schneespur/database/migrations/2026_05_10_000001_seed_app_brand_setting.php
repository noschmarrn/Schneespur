<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the permanent brand from the install locale.
     *
     * The web installer sets `app_brand` explicitly in step 5, but the
     * Docker/Coolify deployment only ever runs `migrate` — no installer. There
     * this migration IS the brand decision, so it must read the configured
     * install locale (APP_LOCALE) instead of hardcoding the German brand.
     * Existing installs are untouched: the migration only fills a NULL value,
     * and it never re-runs on a database that already migrated it.
     */
    public function up(): void
    {
        if (Setting::get('app_brand') !== null) {
            return;
        }

        Setting::set('app_brand', config('app.locale') === 'de' ? 'schneespur' : 'wintertrace');
    }

    public function down(): void
    {
        Setting::where('key', 'app_brand')->delete();
    }
};
