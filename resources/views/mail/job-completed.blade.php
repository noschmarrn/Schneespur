<x-mail::message>
@php $mailContact = $job->customerObject ?? $job->customer; @endphp
{{ __('notification.greeting', ['name' => $mailContact->contact_name ?? $mailContact->name ?? '']) }}

@if($isWeatherUpdate)
{{ __('notification.weather_update_note') }}

@endif
{{ __('notification.job_completed_body', [
    'date' => $job->localStartedAt()->format(__('notification.date_format')),
    'time_start' => $job->localStartedAt()->format('H:i'),
    'time_end' => $job->localEndedAt()?->format('H:i') ?? '–',
    'type' => $job->type->label(),
    'driver' => $job->user->name,
]) }}

@if($weatherAvailable)
@php
    $endWeather = $job->weatherSnapshots->first(fn ($ws) => $ws->moment === \App\Enums\WeatherMoment::End);
    $startWeather = $job->weatherSnapshots->first(fn ($ws) => $ws->moment === \App\Enums\WeatherMoment::Start);
    $weather = $endWeather ?? $startWeather;
@endphp
@if($weather)
{{ __('notification.weather_summary', [
    'temperature' => number_format((float) $weather->temperature, 1),
    'precipitation' => number_format((float) $weather->precipitation, 1),
]) }}
@endif
@else
{{ __('notification.weather_unavailable') }}
@endif

@if($pdfAttached)
{{ __('notification.pdf_attached') }}
@elseif($pdfSkipped)
{{ __('notification.pdf_too_large') }}
@endif

{{ __('notification.regards') }}
</x-mail::message>
