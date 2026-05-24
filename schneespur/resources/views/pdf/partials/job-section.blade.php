{{-- Per-job section for PDF reports. Expects $job (with relations loaded) and $svgTrack. --}}

{{-- Customer Info --}}
@php
    $pdfCustomer = $job->customerObject?->customer ?? $job->customer;
    $pdfObject = $job->customerObject;
    $addrSource = $pdfObject ?? $pdfCustomer;
@endphp
<div class="section">
    <div class="section-title">{{ __('job.pdf_section_customer') }}</div>
    <table class="info-grid">
        <tr>
            <td class="label">{{ __('job.detail_customer') }}</td>
            <td class="value">{{ $pdfCustomer?->name ?? '–' }}</td>
        </tr>
        @if ($pdfObject)
            <tr>
                <td class="label">{{ __('job.detail_object') }}</td>
                <td class="value">{{ $pdfObject->name }}</td>
            </tr>
        @endif
        @if ($addrSource?->street || $addrSource?->zip || $addrSource?->city)
            <tr>
                <td class="label">{{ __('job.pdf_address') }}</td>
                <td class="value">
                    {{ $addrSource->street }}@if($addrSource->street && ($addrSource->zip || $addrSource->city)),@endif
                    {{ $addrSource->zip }} {{ $addrSource->city }}
                </td>
            </tr>
        @endif
        @if ($addrSource?->contact_name)
            <tr>
                <td class="label">{{ __('job.pdf_contact') }}</td>
                <td class="value">{{ $addrSource->contact_name }}</td>
            </tr>
        @endif
    </table>
</div>

{{-- Job Details --}}
<div class="section">
    <div class="section-title">{{ __('job.pdf_section_job') }}</div>
    <table class="info-grid">
        <tr>
            <td class="label">{{ __('job.detail_type') }}</td>
            <td class="value">
                {{ $job->type->label() }}
                @if ($job->is_manual)
                    <span class="badge">{{ __('job.detail_manual_badge') }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">{{ __('job.detail_driver') }}</td>
            <td class="value">{{ $job->user->displayName() }}</td>
        </tr>
        @if ($job->vehicle)
            <tr>
                <td class="label">{{ __('job.detail_vehicle') }}</td>
                <td class="value">{{ $job->vehicle->displayLabel() }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">{{ __('job.detail_started_at') }}</td>
            <td class="value">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('job.detail_ended_at') }}</td>
            <td class="value">
                @if ($job->ended_at)
                    {{ $job->localEndedAt()->format('d.m.Y H:i') }}
                    ({{ $job->durationFormatted() }})
                @else
                    {{ __('job.status_active') }}
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- Notes --}}
@if ($job->notes)
    <div class="section">
        <div class="section-title">{{ __('job.detail_notes') }}</div>
        <div class="notes-box">{!! nl2br(e($job->notes)) !!}</div>
    </div>
@endif

{{-- Weather --}}
@if ($job->weatherSnapshots->where('fetched_at', '!=', null)->isNotEmpty())
    <div class="section">
        <div class="section-title">{{ __('job.detail_weather') }}</div>
        <table class="weather-table">
            <thead>
                <tr>
                    <th>{{ __('weather.col_moment') }}</th>
                    <th>{{ __('weather.col_temperature') }}</th>
                    <th>{{ __('weather.col_precipitation') }}</th>
                    <th>{{ __('weather.col_snow_depth') }}</th>
                    <th>{{ __('weather.col_weather') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($job->weatherSnapshots->whereNotNull('fetched_at') as $snapshot)
                    <tr>
                        <td>{{ $snapshot->moment->label() }}</td>
                        <td>{{ $snapshot->temperature }} &deg;C</td>
                        <td>{{ $snapshot->precipitation }} mm</td>
                        <td>{{ $snapshot->snow_depth }} cm</td>
                        <td>{{ $snapshot->weatherLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- GPS Track --}}
@if ($job->gpsPoints->isNotEmpty() && $svgTrack)
    <div class="section">
        <div class="section-title">{{ __('job.detail_gps_track') }} ({{ $job->gpsPoints->count() }} {{ __('job.pdf_points') }})</div>
        <div class="gps-track">{!! $svgTrack !!}</div>

        @if (isset($gpsTableData) && $gpsTableData['points']->isNotEmpty())
            @if ($gpsTableData['sampled'])
                <p style="font-size: 8pt; color: #64748b; margin: 4px 0;">
                    {{ __('job.pdf_gps_sampled', ['shown' => $gpsTableData['points']->count(), 'total' => $gpsTableData['total']]) }}
                </p>
            @endif
            <table class="weather-table" style="margin-top: 4px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('job.pdf_time') }}</th>
                        <th>{{ __('job.pdf_lat') }}</th>
                        <th>{{ __('job.pdf_lon') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gpsTableData['points'] as $idx => $point)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($point->timestamp)->setTimezone(config('app.display_timezone'))->format('H:i:s') }}</td>
                            <td>{{ number_format($point->lat, 5) }}</td>
                            <td>{{ number_format($point->lon, 5) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

{{-- Photos --}}
@if ($job->jobPhotos->isNotEmpty())
    <div class="section">
        <div class="section-title">{{ __('job.detail_photos') }} ({{ $job->jobPhotos->count() }})</div>
        <table class="photo-grid">
            @foreach ($job->jobPhotos->chunk(2) as $row)
                <tr>
                    @foreach ($row as $photo)
                        <td>
                            @php
                                $photoPath = $photo->annotated_path ?? $photo->file_path;
                                $photoContents = app(\App\Services\Storage\StorageBackendRegistry::class)->retrieveWithFallback($photoPath);
                            @endphp
                            @if ($photoContents)
                                @php
                                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                    $photoMime = $finfo->buffer($photoContents) ?: 'image/jpeg';
                                @endphp
                                <img src="data:{{ $photoMime }};base64,{{ base64_encode($photoContents) }}" alt="{{ $photo->caption ?? '' }}">
                            @endif
                            @if ($photo->caption)
                                <div class="photo-caption">{{ $photo->caption }}</div>
                            @endif
                            @if ($photo->taken_at)
                                <div class="photo-caption">{{ $photo->taken_at->setTimezone(config('app.display_timezone'))->format('H:i') }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
@endif
