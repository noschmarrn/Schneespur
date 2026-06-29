<?php

namespace App\Models;

use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    protected $table = 'service_jobs';

    protected $fillable = [
        'work_shift_id',
        'customer_id',
        'customer_object_id',
        'user_id',
        'vehicle_id',
        'type',
        'started_at',
        'ended_at',
        'notes',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'type' => \App\Casts\JobTypeCast::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_manual' => 'boolean',
        ];
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function customerObject(): BelongsTo
    {
        return $this->belongsTo(CustomerObject::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function gpsPoints(): HasMany
    {
        return $this->hasMany(GpsPoint::class);
    }

    public function weatherSnapshots(): HasMany
    {
        return $this->hasMany(WeatherSnapshot::class);
    }

    public function jobPhotos(): HasMany
    {
        return $this->hasMany(JobPhoto::class);
    }

    public function notificationLogs(): MorphMany
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }

    public function alertDismissals(): HasMany
    {
        return $this->hasMany(AlertDismissal::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(JobAudit::class);
    }

    public function isCompleted(): bool
    {
        return $this->ended_at !== null;
    }

    public function isInGracePeriod(): bool
    {
        return $this->isCompleted() && $this->ended_at->addHours(24)->isFuture();
    }

    public function isLocked(): bool
    {
        return $this->isCompleted() && ! $this->isInGracePeriod();
    }

    public function isGpsLocked(): bool
    {
        return $this->isCompleted();
    }

    public function graceDeadline(): ?Carbon
    {
        return $this->ended_at?->addHours(24);
    }

    public function localStartedAt(): Carbon
    {
        return $this->started_at->copy()->setTimezone(config('app.display_timezone'));
    }

    public function localEndedAt(): ?Carbon
    {
        return $this->ended_at?->copy()->setTimezone(config('app.display_timezone'));
    }

    public function durationFormatted(): string
    {
        if (! $this->ended_at) {
            return '';
        }

        $diff = $this->started_at->diff($this->ended_at);
        $hours = $diff->h + ($diff->days * 24);

        if ($hours > 0 && $diff->i > 0) {
            return __('job.duration_hours_minutes', ['hours' => $hours, 'minutes' => $diff->i]);
        }

        if ($hours > 0) {
            return trans_choice('job.duration_hours', $hours, ['hours' => $hours]);
        }

        return __('job.duration_minutes', ['minutes' => $diff->i ?: 1]);
    }
}
