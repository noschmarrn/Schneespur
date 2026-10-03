<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Preis je Objekt und Einsatzart. Fehlt die Zeile, gilt der Grundpreis
 * am Objekt — siehe App\Services\Pricing\JobPricing::resolve().
 */
class CustomerObjectPrice extends Model
{
    protected $fillable = ['customer_object_id', 'job_type', 'amount_cents', 'unit'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function customerObject(): BelongsTo
    {
        return $this->belongsTo(CustomerObject::class);
    }
}
