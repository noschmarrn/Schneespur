<?php

namespace App\Models;

use App\Enums\WeatherMoment;
use Database\Factories\WeatherSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherSnapshot extends Model
{
    /** @use HasFactory<WeatherSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'job_id',
        'moment',
        'provider',
        'temperature',
        'precipitation',
        'snow_depth',
        'wind_speed',
        'humidity',
        'weather_code',
        'fetched_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'moment' => WeatherMoment::class,
            'temperature' => 'decimal:2',
            'precipitation' => 'decimal:2',
            'snow_depth' => 'decimal:2',
            'wind_speed' => 'decimal:2',
            'humidity' => 'integer',
            'fetched_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function providerLabel(): string
    {
        return __('weather.provider_' . ($this->provider ?? 'openmeteo_free'));
    }

    public function weatherLabel(): string
    {
        $key = 'weather.wmo_' . $this->weather_code;

        if (__($key) !== $key) {
            return __($key);
        }

        return __('weather.wmo_unknown', ['code' => $this->weather_code ?? '–']);
    }
}
