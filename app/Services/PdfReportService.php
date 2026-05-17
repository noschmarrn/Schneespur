<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PdfReportService
{
    public function __construct(
        private GpsSmoothingService $gpsSmoother = new GpsSmoothingService,
    ) {}

    public function generateJobReport(Job $job): \Barryvdh\DomPDF\PDF
    {
        $job->load([
            'customer',
            'customerObject',
            'user',
            'vehicle',
            'gpsPoints' => fn ($q) => $q->orderBy('timestamp'),
            'weatherSnapshots',
            'jobPhotos' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
        ]);

        $smoothedPoints = $this->gpsSmoother->smooth($job->gpsPoints);
        $svgTrack = $this->renderGpsTrackSvg($smoothedPoints);
        $gpsTableData = $this->sampleGpsPointsForTable($job->gpsPoints);

        $pdf = Pdf::loadView('pdf.job-report', [
            'job' => $job,
            'svgTrack' => $svgTrack ? $this->svgToImgTag($svgTrack, $width = 500, $height = 300) : null,
            'gpsTableData' => $gpsTableData,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->render();
        $this->addCanvasFooter($pdf, __('job.pdf_title'));

        return $pdf;
    }

    public function sampleGpsPointsForTable(Collection $gpsPoints, int $maxRows = 30): array
    {
        $total = $gpsPoints->count();

        if ($total <= $maxRows) {
            return ['points' => $gpsPoints, 'total' => $total, 'sampled' => false];
        }

        $headCount = 5;
        $tailCount = 5;
        $middleSlots = $maxRows - $headCount - $tailCount;

        $head = $gpsPoints->slice(0, $headCount);
        $tail = $gpsPoints->slice($total - $tailCount);

        $middleSource = $gpsPoints->slice($headCount, $total - $headCount - $tailCount);
        $middleTotal = $middleSource->count();
        $step = max(1, (int) floor($middleTotal / $middleSlots));

        $middle = $middleSource->values()->filter(fn ($_, $i) => $i % $step === 0)->take($middleSlots);

        $sampled = $head->concat($middle)->concat($tail)->values();

        return ['points' => $sampled, 'total' => $total, 'sampled' => true];
    }

    public function renderGpsTrackSvg(Collection $gpsPoints, int $width = 500, int $height = 300): ?string
    {
        if ($gpsPoints->isEmpty()) {
            return null;
        }

        $lats = $gpsPoints->pluck('lat');
        $lons = $gpsPoints->pluck('lon');

        $minLat = $lats->min();
        $maxLat = $lats->max();
        $minLon = $lons->min();
        $maxLon = $lons->max();

        $latSpan = $maxLat - $minLat;
        $lonSpan = $maxLon - $minLon;

        if ($latSpan == 0 && $lonSpan == 0) {
            return $this->renderSinglePointSvg($gpsPoints->first(), $width, $height);
        }

        $padding = 30;
        $drawWidth = $width - (2 * $padding);
        $drawHeight = $height - (2 * $padding);

        $latSpan = max($latSpan, 0.0001);
        $lonSpan = max($lonSpan, 0.0001);

        $scaleX = $drawWidth / $lonSpan;
        $scaleY = $drawHeight / $latSpan;
        $scale = min($scaleX, $scaleY);

        $scaledWidth = $lonSpan * $scale;
        $scaledHeight = $latSpan * $scale;
        $offsetX = $padding + ($drawWidth - $scaledWidth) / 2;
        $offsetY = $padding + ($drawHeight - $scaledHeight) / 2;

        $points = $gpsPoints->map(function ($p) use ($minLat, $maxLat, $minLon, $scale, $offsetX, $offsetY) {
            $x = round(($p->lon - $minLon) * $scale + $offsetX, 1);
            $y = round(($maxLat - $p->lat) * $scale + $offsetY, 1);
            return "$x,$y";
        })->implode(' ');

        $first = $gpsPoints->first();
        $last = $gpsPoints->last();
        $startX = round(($first->lon - $minLon) * $scale + $offsetX, 1);
        $startY = round(($maxLat - $first->lat) * $scale + $offsetY, 1);
        $endX = round(($last->lon - $minLon) * $scale + $offsetX, 1);
        $endY = round(($maxLat - $last->lat) * $scale + $offsetY, 1);

        $startLabel = __('job.pdf_track_start');
        $endLabel = __('job.pdf_track_end');

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}">
            <rect x="0" y="0" width="{$width}" height="{$height}" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
            <polyline points="{$points}" fill="none" stroke="#4f46e5" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" opacity="0.8"/>
            <circle cx="{$startX}" cy="{$startY}" r="5" fill="#16a34a"/>
            <text x="{$startX}" y="{$startY}" dx="8" dy="4" font-size="11" fill="#16a34a" font-family="DejaVu Sans, sans-serif">{$startLabel}</text>
            <circle cx="{$endX}" cy="{$endY}" r="5" fill="#dc2626"/>
            <text x="{$endX}" y="{$endY}" dx="8" dy="4" font-size="11" fill="#dc2626" font-family="DejaVu Sans, sans-serif">{$endLabel}</text>
        </svg>
        SVG;
    }

    private function addCanvasFooter(\Barryvdh\DomPDF\PDF $pdf, string $rightText): void
    {
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $size = 7;
        $color = [0.58, 0.64, 0.72];
        $lineColor = [0.89, 0.91, 0.94];
        $leftText = __('job.pdf_generated_at', ['date' => now()->format('d.m.Y H:i')]);
        $y = $canvas->get_height() - 30;
        $xLeft = 42;
        $xRight = $canvas->get_width() - 42;

        $canvas->page_line($xLeft, $y, $xRight, $y, $lineColor, 0.5);
        $canvas->page_text($xLeft, $y + 5, $leftText, $font, $size, $color);
        $canvas->page_text($xRight - $pdf->getDomPDF()->getFontMetrics()->getTextWidth($rightText, $font, $size), $y + 5, $rightText, $font, $size, $color);
    }

    private function svgToImgTag(string $svg, int $width, int $height): string
    {
        return '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="' . $width . '" height="' . $height . '">';
    }

    private function renderSinglePointSvg(mixed $point, int $width, int $height): string
    {
        $cx = $width / 2;
        $cy = $height / 2;
        $label = number_format($point->lat, 5) . ', ' . number_format($point->lon, 5);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}" width="{$width}" height="{$height}">
            <rect width="{$width}" height="{$height}" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" rx="4"/>
            <circle cx="{$cx}" cy="{$cy}" r="6" fill="#4f46e5"/>
            <text x="{$cx}" y="{$cy}" dy="20" text-anchor="middle" font-size="11" fill="#64748b" font-family="DejaVu Sans, sans-serif">{$label}</text>
        </svg>
        SVG;
    }

    public function generateCustomerReport(Customer $customer, Carbon $from, Carbon $to, bool $includeActive = false): \Barryvdh\DomPDF\PDF
    {
        $query = $customer->serviceJobs()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<=', $to->copy()->endOfDay());

        if (! $includeActive) {
            $query->whereNotNull('ended_at');
        }

        $jobs = $query->orderBy('started_at', 'asc')
            ->with([
                'customer',
                'customerObject',
                'user',
                'vehicle',
                'gpsPoints' => fn ($q) => $q->orderBy('timestamp'),
                'weatherSnapshots',
                'jobPhotos' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
            ])
            ->get();

        $jobData = [];
        foreach ($jobs as $job) {
            $svg = $this->renderGpsTrackSvg($this->gpsSmoother->smooth($job->gpsPoints));
            $jobData[$job->id] = [
                'svgTrack' => $svg ? $this->svgToImgTag($svg, 500, 300) : null,
                'gpsTableData' => $this->sampleGpsPointsForTable($job->gpsPoints),
            ];
        }

        $coverData = $this->buildCoverData($jobs);

        $customer->loadMissing('objects');

        $pdf = Pdf::loadView('pdf.customer-report', [
            'customer' => $customer,
            'jobs' => $jobs,
            'jobData' => $jobData,
            'from' => $from,
            'to' => $to,
            'coverData' => $coverData,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->render();
        $this->addCanvasFooter($pdf, __('job.pdf_customer_report_title'));

        return $pdf;
    }

    private function buildCoverData(Collection $jobs): array
    {
        $typeBreakdown = [];
        foreach ($jobs as $job) {
            $label = $job->type->label();
            $typeBreakdown[$label] = ($typeBreakdown[$label] ?? 0) + 1;
        }

        $totalMinutes = $jobs->sum(function ($job) {
            return $job->ended_at
                ? $job->started_at->diffInMinutes($job->ended_at)
                : 0;
        });

        $allSnapshots = $jobs->flatMap(
            fn ($job) => $job->weatherSnapshots->whereNotNull('fetched_at')
        );

        $weather = ['hasData' => false, 'minTemp' => null, 'maxTemp' => null, 'topConditions' => [], 'jobsWithoutWeather' => 0];

        if ($allSnapshots->isNotEmpty()) {
            $weather['hasData'] = true;
            $weather['minTemp'] = $allSnapshots->min('temperature');
            $weather['maxTemp'] = $allSnapshots->max('temperature');

            $codeCounts = $allSnapshots->groupBy('weather_code')
                ->map->count()
                ->sortDesc()
                ->take(3);

            $weather['topConditions'] = $codeCounts->map(function ($count, $code) {
                $key = 'weather.wmo_' . $code;
                $label = __($key) !== $key ? __($key) : __('weather.wmo_unknown', ['code' => $code]);
                return ['label' => $label, 'count' => $count];
            })->values()->all();
        }

        $weather['jobsWithoutWeather'] = $jobs->filter(
            fn ($job) => $job->weatherSnapshots->whereNotNull('fetched_at')->isEmpty()
        )->count();

        return [
            'totalJobs' => $jobs->count(),
            'typeBreakdown' => $typeBreakdown,
            'totalMinutes' => $totalMinutes,
            'weather' => $weather,
        ];
    }

    public function generateObjectReport(CustomerObject $object, Carbon $from, Carbon $to, bool $includeActive = false): \Barryvdh\DomPDF\PDF
    {
        $query = $object->serviceJobs()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<=', $to->copy()->endOfDay());

        if (! $includeActive) {
            $query->whereNotNull('ended_at');
        }

        $jobs = $query->orderBy('started_at', 'asc')
            ->with([
                'customer',
                'customerObject',
                'user',
                'vehicle',
                'gpsPoints' => fn ($q) => $q->orderBy('timestamp'),
                'weatherSnapshots',
                'jobPhotos' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
            ])
            ->get();

        $jobData = [];
        foreach ($jobs as $job) {
            $svg = $this->renderGpsTrackSvg($this->gpsSmoother->smooth($job->gpsPoints));
            $jobData[$job->id] = [
                'svgTrack' => $svg ? $this->svgToImgTag($svg, 500, 300) : null,
                'gpsTableData' => $this->sampleGpsPointsForTable($job->gpsPoints),
            ];
        }

        $coverData = $this->buildCoverData($jobs);

        $pdf = Pdf::loadView('pdf.customer-report', [
            'customer' => $object->customer,
            'customerObject' => $object,
            'jobs' => $jobs,
            'jobData' => $jobData,
            'from' => $from,
            'to' => $to,
            'coverData' => $coverData,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->render();
        $this->addCanvasFooter($pdf, __('job.pdf_customer_report_title'));

        return $pdf;
    }

    public function objectReportFilename(CustomerObject $object, Carbon $from, Carbon $to): string
    {
        $customerSlug = str($object->customer->name)->slug();
        $objectSlug = str($object->name)->slug();

        return "sammel-nachweis-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}-{$customerSlug}-{$objectSlug}.pdf";
    }

    public function customerReportFilename(Customer $customer, Carbon $from, Carbon $to): string
    {
        $slug = str($customer->name)->slug();
        return "sammel-nachweis-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}-{$slug}.pdf";
    }

    public function jobReportFilename(Job $job): string
    {
        $date = $job->started_at->format('Y-m-d');
        $customer = str($job->customerObject?->customer?->name ?? $job->customer?->name ?? 'unknown')->slug();
        $object = $job->customerObject ? '-' . str($job->customerObject->name)->slug() : '';
        return "einsatznachweis-{$date}-{$customer}{$object}-{$job->id}.pdf";
    }
}
