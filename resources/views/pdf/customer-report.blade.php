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

        /* TOC */
        .toc-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .toc-table th { background: #f1f5f9; text-align: left; padding: 5px 8px; font-weight: bold; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .toc-table td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }

        /* Page breaks */
        .page-break-before { page-break-before: always; }
    </style>
</head>
<body>
    {{-- Cover page --}}
    {{-- Header --}}
    <div class="header">
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
        <table class="header-table">
            <tr>
                @if ($logoAbsPath && file_exists($logoAbsPath))
                    <td style="width: 60px;">
                        <img src="{{ $logoAbsPath }}" class="header-logo" alt="">
                    </td>
                @endif
                <td>
                    <h1>{{ __('job.pdf_customer_report_title') }}</h1>
                    <div class="subtitle">{{ __('job.pdf_customer_report_subtitle', ['customer' => $customer->name, 'from' => $from->format('d.m.Y'), 'to' => $to->format('d.m.Y')]) }}</div>
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

    {{-- Customer info --}}
    <div class="section">
        <div class="section-title">{{ __('job.pdf_section_customer') }}</div>
        <table class="info-grid">
            <tr>
                <td class="label">{{ __('job.detail_customer') }}</td>
                <td class="value">{{ $customer->name }}</td>
            </tr>
            @php $primaryObject = $customerObject ?? $customer->objects->first(); @endphp
            @if ($primaryObject && ($primaryObject->street || $primaryObject->zip || $primaryObject->city))
                <tr>
                    <td class="label">{{ __('job.pdf_address') }}</td>
                    <td class="value">
                        {{ $primaryObject->street }}@if($primaryObject->street && ($primaryObject->zip || $primaryObject->city)),@endif
                        {{ $primaryObject->zip }} {{ $primaryObject->city }}
                    </td>
                </tr>
            @endif
            @if ($customer->contact_name)
                <tr>
                    <td class="label">{{ __('job.pdf_contact') }}</td>
                    <td class="value">{{ $customer->contact_name }}</td>
                </tr>
            @endif
            @if ($customer->phone)
                <tr>
                    <td class="label">{{ __('job.pdf_cover_phone') }}</td>
                    <td class="value">{{ $customer->phone }}</td>
                </tr>
            @endif
            @if ($customer->email)
                <tr>
                    <td class="label">{{ __('job.pdf_cover_email') }}</td>
                    <td class="value">{{ $customer->email }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if ($jobs->isEmpty())
        <div class="section">
            <p>{{ __('job.pdf_no_jobs_in_range') }}</p>
        </div>
    @else
        {{-- Cover Summary --}}
        <div class="section">
            <div class="section-title">{{ __('job.pdf_cover_summary') }}</div>
            <table class="info-grid">
                <tr>
                    <td class="label">{{ __('job.pdf_cover_total_jobs') }}</td>
                    <td class="value">{{ $coverData['totalJobs'] }}</td>
                </tr>
                <tr>
                    <td class="label">{{ __('job.pdf_cover_total_duration') }}</td>
                    <td class="value">{{ intdiv((int) $coverData['totalMinutes'], 60) }}:{{ str_pad((int) $coverData['totalMinutes'] % 60, 2, '0', STR_PAD_LEFT) }} h</td>
                </tr>
            </table>
        </div>

        {{-- Job type breakdown --}}
        <div class="section">
            <div class="section-title">{{ __('job.pdf_cover_job_types') }}</div>
            <table class="info-grid">
                @foreach ($coverData['typeBreakdown'] as $typeLabel => $count)
                    <tr>
                        <td class="label">{{ $typeLabel }}</td>
                        <td class="value">{{ $count }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

        {{-- Weather aggregation --}}
        <div class="section">
            <div class="section-title">{{ __('job.pdf_cover_weather_summary') }}</div>
            @if ($coverData['weather']['hasData'])
                <table class="info-grid">
                    <tr>
                        <td class="label">{{ __('job.pdf_cover_temp_range') }}</td>
                        <td class="value">{{ $coverData['weather']['minTemp'] }} &ndash; {{ $coverData['weather']['maxTemp'] }} &deg;C</td>
                    </tr>
                    @if (! empty($coverData['weather']['topConditions']))
                        <tr>
                            <td class="label">{{ __('job.pdf_cover_conditions') }}</td>
                            <td class="value">
                                @foreach ($coverData['weather']['topConditions'] as $condition)
                                    {{ $condition['label'] }} ({{ $condition['count'] }})@if (! $loop->last), @endif
                                @endforeach
                            </td>
                        </tr>
                    @endif
                </table>
            @endif
            @if ($coverData['weather']['jobsWithoutWeather'] > 0)
                <p style="font-size: 9pt; color: #64748b; margin-top: 4px;">
                    {{ trans_choice('job.pdf_cover_no_weather', $coverData['weather']['jobsWithoutWeather'], ['count' => $coverData['weather']['jobsWithoutWeather']]) }}
                </p>
            @endif
        </div>

        {{-- Table of Contents --}}
        <div class="section">
            <div class="section-title">{{ __('job.pdf_cover_toc') }}</div>
            <table class="toc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('job.pdf_cover_toc_date') }}</th>
                        <th>{{ __('job.detail_object') }}</th>
                        <th>{{ __('job.pdf_cover_toc_type') }}</th>
                        <th>{{ __('job.pdf_cover_toc_duration') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jobs as $index => $job)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $job->localStartedAt()->format('d.m.Y H:i') }}</td>
                            <td>{{ $job->customerObject?->name ?? '–' }}</td>
                            <td>{{ $job->type->label() }}</td>
                            <td>
                                @if ($job->ended_at)
                                    {{ $job->durationFormatted() }}
                                @else
                                    <span class="badge">{{ __('job.pdf_cover_active_marker') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Individual job pages --}}
    @if ($jobs->isNotEmpty())
        @foreach ($jobs as $index => $job)
            <div class="page-break-before">
                @include('pdf.partials.job-section', [
                    'job' => $job,
                    'svgTrack' => $jobData[$job->id]['svgTrack'],
                    'gpsTableData' => $jobData[$job->id]['gpsTableData'],
                ])
            </div>
        @endforeach
    @endif
</body>
</html>
