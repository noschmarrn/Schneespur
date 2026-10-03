<?php

namespace App\Models;

use Database\Factories\CustomerObjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerObject extends Model
{
    /** @use HasFactory<CustomerObjectFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'street',
        'zip',
        'city',
        'state',
        'lat',
        'lon',
        'contact_name',
        'contact_email',
        'contact_phone',
        'price_amount_cents',
        'price_unit',
        'plow_threshold_value',
        'plow_threshold_unit',
        'salt_enabled',
        'site_notes',
        'access_notes',
        'notify_recipients',
        'auto_notify_email',
        'notification_email',
    ];

    protected function casts(): array
    {
        return [
            'price_amount_cents' => 'integer',
            'plow_threshold_value' => 'decimal:2',
            'salt_enabled' => 'boolean',
            'auto_notify_email' => 'boolean',
            'lat' => 'decimal:7',
            'lon' => 'decimal:7',
        ];
    }

    /**
     * Die Räumschwelle in Millimetern — die eine Stelle, an der aus zwei
     * Spalten eine Zahl wird.
     *
     * Zwei Spalten zu speichern hält die Eingabe des Betriebs verlustfrei
     * (2 Zoll bleiben 2 Zoll), darf aber nicht bedeuten, daß jeder Leser beide
     * kennen muß. Wer vergleichen oder rechnen will — der Fahrerkasten aus
     * Phase 4, ein Modul —, ruft diese Methode und sonst nichts.
     *
     * Null heißt "keine Schwelle gesetzt", nicht "ab 0 mm räumen". Dieselbe
     * Unterscheidung, die Phase 2 für die Schneehöhe erzwungen hat.
     */
    public function plowThresholdMm(): ?float
    {
        if ($this->plow_threshold_value === null) {
            return null;
        }

        $value = (float) $this->plow_threshold_value;

        return match ($this->plow_threshold_unit) {
            'in' => round($value * 25.4, 2),
            'cm' => round($value * 10, 2),
            // Eine Einheit, die weder cm noch in ist, ist keine Angabe, aus der
            // sich räumen läßt. Sie still als Zentimeter zu lesen ergäbe ein
            // Vielfaches daneben — dieselbe Falle wie 0.0 statt „unbekannt".
            default => null,
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CustomerObjectPrice::class);
    }

    /**
     * Preise je Einsatzart setzen. Schlüssel ist die Einsatzart; ein Wert
     * null löscht die Zeile ("Grundpreis gilt"). Einsatzarten, die im Array
     * nicht vorkommen, bleiben unberührt.
     *
     * @param array<string, array{amount_cents:int, unit:string}|null> $rows
     */
    public function syncPrices(array $rows): void
    {
        foreach ($rows as $jobType => $row) {
            if ($row === null) {
                $this->prices()->where('job_type', $jobType)->delete();

                continue;
            }

            $this->prices()->updateOrCreate(
                ['job_type' => $jobType],
                ['amount_cents' => $row['amount_cents'], 'unit' => $row['unit']],
            );
        }
    }
}
