<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'license_plate',
        'owntracks_device_id',
        'notes',
    ];

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function displayLabel(): string
    {
        return $this->license_plate
            ? "{$this->name} ({$this->license_plate})"
            : $this->name;
    }
}
