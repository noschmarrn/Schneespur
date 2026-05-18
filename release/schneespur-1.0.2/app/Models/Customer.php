<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'auto_notify_email',
        'notification_email',
        'locale',
        'password',
        'portal_enabled',
        'portal_show_gps',
        'portal_show_photos',
        'portal_show_driver_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'auto_notify_email' => 'boolean',
            'password' => 'hashed',
            'portal_enabled' => 'boolean',
            'portal_show_gps' => 'boolean',
            'portal_show_photos' => 'boolean',
            'portal_show_driver_name' => 'boolean',
        ];
    }

    public function objects(): HasMany
    {
        return $this->hasMany(CustomerObject::class);
    }

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function notificationLogs(): MorphMany
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }
}
