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

/**
 * Selectable brands: slug => display name.
 *
 * Single source of truth for the admin brand picker, the validation rule and
 * brand() itself, so adding a brand never means editing three places.
 */
function brand_options(): array
{
    return [
        'schneespur' => 'Schneespur',
        'wintertrace' => 'Wintertrace',
    ];
}

function brand(): string
{
    return brand_options()[brand_slug()] ?? 'Schneespur';
}

/**
 * Resolve the outgoing mail "from" name.
 *
 * config/mail.php falls back to APP_NAME, which ships as "Schneespur" in the
 * stock .env — so a Wintertrace install would send mail under the German
 * brand. An operator-chosen name always wins; an unset one follows the brand.
 *
 * @param  string|null  $configured  Current config('mail.from.name') value
 */
function brand_mail_from_name(?string $configured): string
{
    $configured = trim((string) $configured);

    if ($configured === '') {
        return brand();
    }

    // TODO(Michael): decide how to treat a from-name that is merely the stock
    // .env default (MAIL_FROM_NAME="${APP_NAME}" => "Schneespur"/"Laravel")
    // rather than a deliberate operator choice. See the note in the session.

    return $configured;
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

/**
 * Render Markdown to HTML with unsafe link schemes neutralised.
 *
 * This is the ONLY place in the application that turns Markdown into HTML.
 * `html_input: strip` removes raw HTML, but on its own it leaves Markdown link
 * targets untouched — CommonMark's `allow_unsafe_links` defaults to true, so
 * `[x](javascript:…)` would render as a working anchor (M018/S02).
 */
function markdown_safe(string $markdown): string
{
    return \Illuminate\Support\Str::markdown($markdown, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
}

function format_date(?\DateTimeInterface $moment): string
{
    return app(\App\Services\Format\Formatter::class)->date($moment);
}

function format_time(?\DateTimeInterface $moment, bool $withSeconds = false): string
{
    return app(\App\Services\Format\Formatter::class)->time($moment, $withSeconds);
}

function format_datetime(?\DateTimeInterface $moment): string
{
    return app(\App\Services\Format\Formatter::class)->dateTime($moment);
}

function format_number(?float $value, int $decimals = 1): string
{
    return app(\App\Services\Format\Formatter::class)->number($value, $decimals);
}

function format_money(?int $cents): string
{
    return app(\App\Services\Format\Formatter::class)->money($cents);
}

function format_temperature(?float $celsius): string
{
    return app(\App\Services\Format\Formatter::class)->temperature($celsius);
}

function format_speed(?float $kmh): string
{
    return app(\App\Services\Format\Formatter::class)->speed($kmh);
}

function format_distance(?float $metres): string
{
    return app(\App\Services\Format\Formatter::class)->distance($metres);
}

function format_weight(?float $kilograms): string
{
    return app(\App\Services\Format\Formatter::class)->weight($kilograms);
}
