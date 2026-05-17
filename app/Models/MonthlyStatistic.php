<?php

namespace App\Models;

use Database\Factories\MonthlyStatisticFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyStatistic extends Model
{
    /** @use HasFactory<MonthlyStatisticFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'total_jobs',
        'raumen_count',
        'streuen_count',
        'kontrolle_count',
        'raumen_streuen_count',
        'manual_count',
        'total_gps_points',
        'total_photos',
        'total_duration_minutes',
        'avg_temperature',
        'unique_customers',
        'unique_drivers',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'avg_temperature' => 'decimal:2',
        ];
    }
}
