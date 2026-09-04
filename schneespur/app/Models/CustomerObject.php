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

        return $this->plow_threshold_unit === 'in'
            ? round($value * 25.4, 2)
            : round($value * 10, 2);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
