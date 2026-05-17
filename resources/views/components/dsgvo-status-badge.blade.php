@props(['user'])

@php
    $confirmed = $user->dsgvo_informed_at
        && $user->confirmed_version >= \App\Models\Setting::get('dsgvo_template_version', 1);
@endphp

@if ($confirmed)
    <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
        {{ __('driver.dsgvo_status_confirmed') }}
    </span>
@else
    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
        {{ __('driver.dsgvo_status_pending') }}
    </span>
@endif
