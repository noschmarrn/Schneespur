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

/**
 * Replace company/DPO placeholders in DSGVO/GDPR template markdown.
 *
 * Knows both German and English placeholder strings so the same Settings
 * values feed into either template language. Missing settings leave the
 * placeholder visible so admins notice gaps.
 */
function dsgvo_apply_company_placeholders(string $text): string
{
    $companyName = Setting::get('company_name', '');
    $street = Setting::get('company_street', '');
    $zip = Setting::get('company_zip', '');
    $city = Setting::get('company_city', '');
    $email = Setting::get('company_email', '');
    $dpo = Setting::get('dpo_contact', '');
    $dpoEmail = Setting::get('dpo_email', '');

    $address = trim("$street, $zip $city", ', ');

    $map = [
        // German placeholders (default-template.blade.php)
        '[Firmenname eintragen]' => $companyName,
        '[Adresse eintragen]' => $address,
        '[E-Mail-Adresse eintragen]' => $email,
        '[DPO-E-Mail-Adresse eintragen]' => $dpoEmail,
        '[Datenschutzbeauftragter / Ansprechpartner eintragen]' => $dpo,
        // English placeholders (default-template-en.blade.php)
        '[Company name]' => $companyName,
        '[Address]' => $address,
        '[Email]' => $email,
        '[DPO email]' => $dpoEmail,
        '[Data Protection Officer / Contact]' => $dpo,
    ];

    foreach ($map as $token => $value) {
        if ($value !== '') {
            $text = str_replace($token, $value, $text);
        }
    }

    return $text;
}

function module_asset(string $slug, string $file): string
{
    return '/modules/' . $slug . '/' . ltrim($file, '/');
}
