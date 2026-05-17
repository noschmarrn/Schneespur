<?php

use App\Models\Setting;

function brand_slug(): string
{
    try {
        $slug = Setting::get('app_brand');
        if ($slug !== null) {
            return $slug;
        }
    } catch (\Throwable) {
        // Settings table not yet migrated (early installer steps) — fall through to locale-based default.
    }

    return app()->getLocale() === 'de' ? 'schneespur' : 'wintertrace';
}

function brand(): string
{
    return match (brand_slug()) {
        'wintertrace' => 'Wintertrace',
        default => 'Schneespur',
    };
}
