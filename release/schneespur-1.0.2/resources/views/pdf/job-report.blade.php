<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm 15mm 18mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.5; }

        /* Header */
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 16px; }
        .header-table { width: 100%; }
        .header-table td { vertical-align: middle; }
        .header-logo { max-height: 50px; margin-right: 10px; }
        .header h1 { font-size: 16pt; color: #4f46e5; margin: 0 0 4px 0; }
        .header .subtitle { font-size: 9pt; color: #64748b; }
        .header .company { font-size: 9pt; color: #64748b; }

        /* Section */
        .section { margin-bottom: 14px; }
        .section-title { font-size: 11pt; font-weight: bold; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 8px; }

        /* Info Grid */
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 3px 8px 3px 0; vertical-align: top; font-size: 10pt; }
        .info-grid .label { font-weight: bold; color: #475569; width: 130px; white-space: nowrap; }
        .info-grid .value { color: #1e293b; }
        .badge { display: inline-block; background: #fef9c3; color: #854d0e; font-size: 8pt; padding: 1px 6px; border-radius: 3px; }

        /* Weather Table */
        .weather-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .weather-table th { background: #f1f5f9; text-align: left; padding: 5px 8px; font-weight: bold; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .weather-table td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }

        /* GPS */
        .gps-track { text-align: center; margin: 6px 0; }
        .gps-track svg { max-width: 100%; }

        /* Photos */
        .photo-grid { width: 100%; }
        .photo-grid td { padding: 4px; vertical-align: top; text-align: center; }
        .photo-grid img { max-width: 240px; max-height: 200px; border: 1px solid #e2e8f0; }
        .photo-caption { font-size: 8pt; color: #64748b; margin-top: 2px; }

        .notes-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; font-size: 9pt; }

        .page-break-before { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $companyName = \App\Models\Setting::get('company_name', config('app.name'));
        $companyStreet = \App\Models\Setting::get('company_street', '');
        $companyZip = \App\Models\Setting::get('company_zip', '');
        $companyCity = \App\Models\Setting::get('company_city', '');
        $companyPhone = \App\Models\Setting::get('company_phone', '');
        $companyEmail = \App\Models\Setting::get('company_email', '');
        $logoPath = \App\Models\Setting::get('company_logo_path');
        $logoAbsPath = $logoPath ? storage_path('app/public/' . $logoPath) : null;
    @endphp

    {{-- Header with company + customer --}}
    <div class="header">
        <table class="header-table">
            <tr>
                @if ($logoAbsPath && file_exists($logoAbsPath))
                    <td style="width: 60px;">
                        <img src="{{ $logoAbsPath }}" class="header-logo" alt="">
                    </td>
                @endif
                <td>
                    <h1>{{ __('job.pdf_title') }}</h1>
                    <div class="subtitle">{{ __('job.pdf_subtitle', ['id' => $job->id, 'date' => $job->localStartedAt()->format('d.m.Y')]) }}</div>
                    <div class="subtitle" style="margin-top: 2px; font-size: 10pt; color: #1e293b; font-weight: bold;">{{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '–' }}@if($job->customerObject) — {{ $job->customerObject->name }}@endif</div>
                </td>
                @if ($companyName)
                    <td style="text-align: right;">
                        <div class="company" style="font-size: 10pt; font-weight: bold; color: #1e293b;">{{ $companyName }}</div>
                        @if ($companyStreet)
                            <div class="company">{{ $companyStreet }}</div>
                        @endif
                        @if ($companyZip || $companyCity)
                            <div class="company">{{ $companyZip }} {{ $companyCity }}</div>
                        @endif
                        @if ($companyPhone)
                            <div class="company">{{ $companyPhone }}</div>
                        @endif
                        @if ($companyEmail)
                            <div class="company">{{ $companyEmail }}</div>
                        @endif
                    </td>
                @endif
            </tr>
        </table>
    </div>

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
        <div class="section page-break-before">
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
                                    $photoAbsPath = storage_path('app/public/' . $photoPath);
                                @endphp
                                @if (file_exists($photoAbsPath))
                                    <img src="{{ $photoAbsPath }}" alt="{{ $photo->caption ?? '' }}">
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
</body>
</html>
