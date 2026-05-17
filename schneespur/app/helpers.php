<?php

use App\Models\Setting;

function brand(): string
{
    $slug = Setting::get('app_brand', 'schneespur');

    return match ($slug) {
        'wintertrace' => 'Wintertrace',
        default => 'Schneespur',
    };
}

function brand_slug(): string
{
    return Setting::get('app_brand', 'schneespur');
}
