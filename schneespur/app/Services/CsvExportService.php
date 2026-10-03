<?php

namespace App\Services;

use App\Models\Job;
use App\Services\Format\Formatter;
use Illuminate\Support\Carbon;
use League\Csv\ByteSequence;
use League\Csv\Writer;

class CsvExportService
{
    public function buildCsv(string $variant, ?string $from, ?string $to, ?int $userId = null, ?int $customerId = null): string
    {
        $csv = Writer::createFromString();
        $csv->setOutputBOM(ByteSequence::BOM_UTF8);
        // Kein festes Semikolon: Excel im englischen Sprachraum erwartet ein
        // Komma und legt sonst die ganze Zeile in eine Spalte.
        $csv->setDelimiter(app(Formatter::class)->csvDelimiter());

        $csv->insertOne([
            __('export.csv_col_date'),
            __('export.csv_col_driver'),
            __('export.csv_col_customer'),
            __('export.csv_col_object_name'),
            __('export.csv_col_object_street'),
            __('export.csv_col_object_zip'),
            __('export.csv_col_object_city'),
            __('export.csv_col_object_state'),
            __('export.csv_col_type'),
            __('export.csv_col_start'),
            __('export.csv_col_end'),
            __('export.csv_col_duration'),
            __('export.csv_col_shift_id'),
            __('export.csv_col_source'),
            __('export.csv_col_amount'),
            __('export.csv_col_price_unit'),
            __('export.csv_col_price_rate'),
        ]);

        $query = Job::query()
            ->with([
                'customer:id,name',
                'customerObject:id,customer_id,name,street,zip,city,state',
                'user' => fn ($q) => $q->withAnonymized(),
                'workShift:id',
            ])
            ->when($from, fn ($q, $d) => $q->where('started_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->where('started_at', '<=', $d . ' 23:59:59'))
            ->orderBy('started_at');

        if ($variant === 'driver' && $userId) {
            $query->where('user_id', $userId);
        }

        if ($variant === 'customer' && $customerId) {
            $query->where('customer_id', $customerId);
        }

        $now = Carbon::now();

        foreach ($query->lazy(200) as $job) {
            $duration = '';
            if ($job->ended_at) {
                $duration = (string) (int) round($job->started_at->diffInSeconds($job->ended_at) / 60);
            } elseif ($job->started_at) {
                $duration = (string) (int) round($job->started_at->diffInSeconds($now) / 60);
            }

            $csv->insertOne([
                format_date($job->started_at),
                $job->user?->displayName() ?? '',
                $job->customer?->name ?? '',
                $job->customerObject?->name ?? '',
                $job->customerObject?->street ?? '',
                $job->customerObject?->zip ?? '',
                $job->customerObject?->city ?? '',
                $job->customerObject?->state ?? '',
                $job->type->label(),
                format_time($job->started_at),
                format_time($job->ended_at),
                $duration,
                $job->work_shift_id ?? '',
                $job->is_manual ? __('export.csv_source_manual') : __('export.csv_source_automatic'),
                // Ohne Währungszeichen und im Zahlenformat des Betriebs: eine
                // Tabellenkalkulation soll damit rechnen können. Leer heißt
                // "kein Betrag" — nicht 0.
                $job->price_amount_cents === null ? '' : format_number($job->price_amount_cents / 100, 2),
                $job->price_unit === null ? '' : __('customer_object.price_unit_'.$job->price_unit),
                $job->price_rate_cents === null ? '' : format_number($job->price_rate_cents / 100, 2),
            ]);
        }

        return $csv->toString();
    }

    public function generateFilename(string $variant, ?string $from, ?string $to): string
    {
        $parts = ['zeiterfassung', $variant];

        if ($from) {
            $parts[] = $from;
        }
        if ($to) {
            $parts[] = $to;
        }

        return implode('-', $parts) . '.csv';
    }
}
