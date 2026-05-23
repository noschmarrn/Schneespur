<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Scopes\ExcludeAnonymizedScope;
use App\Models\Traits\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'owntracks_username',
        'phone',
        'notes',
        'default_vehicle_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'owntracks_password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'dsgvo_informed_at' => 'datetime',
            'confirmed_version' => 'integer',
            'anonymized_at' => 'datetime',
            'owntracks_password_revealed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ExcludeAnonymizedScope);

        static::saved(function (User $user) {
            if (! $user->wasChanged('role') || $user->role === null) {
                return;
            }

            $role = Role::where('slug', $user->role->value)->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching($role->id);
            }
        });
    }

    // --- Relations ---

    public function dsgvoConfirmations(): HasMany
    {
        return $this->hasMany(DsgvoConfirmation::class, 'driver_id');
    }

    public function owntracksCredentialEvents(): HasMany
    {
        return $this->hasMany(OwntracksCredentialEvent::class, 'driver_id');
    }

    public function workShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class);
    }

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function gpsPoints(): HasMany
    {
        return $this->hasMany(GpsPoint::class);
    }

    public function defaultVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'default_vehicle_id');
    }

    public function latestDsgvoConfirmation(): ?DsgvoConfirmation
    {
        return $this->dsgvoConfirmations()->latest('confirmed_at')->first();
    }

    // --- Helpers ---

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isDriver(): bool
    {
        return $this->hasRole('driver');
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function displayName(): string
    {
        if ($this->isAnonymized()) {
            return __('driver.anonymized_display_name', ['id' => $this->id]);
        }

        return $this->name;
    }

    // --- Scopes ---

    public function scopeDrivers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q) => $q->where('slug', 'driver'));
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q) => $q->where('slug', 'admin'));
    }

    public function scopeWithAnonymized(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ExcludeAnonymizedScope::class);
    }

    public function scopeOnlyAnonymized(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ExcludeAnonymizedScope::class)
            ->whereNotNull('anonymized_at');
    }
}
