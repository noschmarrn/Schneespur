<?php

namespace App\Models;

use App\Models\Scopes\ExcludeAnonymizedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// insert-only audit table — updates and deletes are blocked
class OwntracksCredentialEvent extends Model
{
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'driver_id',
        'event',
        'actor_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('owntracks_credential_events is insert-only'));
        static::deleting(fn () => throw new \LogicException('owntracks_credential_events is insert-only'));
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id')
            ->withoutGlobalScope(ExcludeAnonymizedScope::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
