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
        'lat',
        'lon',
        'contact_name',
        'contact_email',
        'contact_phone',
        'price_amount_cents',
        'price_unit',
        'plow_threshold_cm',
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
            'plow_threshold_cm' => 'integer',
            'salt_enabled' => 'boolean',
            'auto_notify_email' => 'boolean',
            'lat' => 'decimal:7',
            'lon' => 'decimal:7',
        ];
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
