<?php

namespace App\Models;

use Database\Factories\GpsPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsPoint extends Model
{
    /** @use HasFactory<GpsPointFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_id',
        'lat',
        'lon',
        'timestamp',
        'altitude',
        'battery',
        'velocity',
        'accuracy',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'timestamp' => 'integer',
            'altitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
