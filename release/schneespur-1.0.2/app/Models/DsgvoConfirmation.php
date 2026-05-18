<?php

namespace App\Models;

use App\Models\Scopes\ExcludeAnonymizedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// insert-only audit table — updates and deletes are blocked
class DsgvoConfirmation extends Model
{
    public $timestamps = false;

    protected $table = 'driver_dsgvo_confirmations';

    protected $fillable = [
        'driver_id',
        'confirmed_at',
        'signed_by',
        'notice_text_snapshot',
        'notice_language',
        'template_version',
        'ip_address',
        'user_agent',
        'app_version',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'template_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('driver_dsgvo_confirmations is insert-only'));
        static::deleting(fn () => throw new \LogicException('driver_dsgvo_confirmations is insert-only'));
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id')
            ->withoutGlobalScope(ExcludeAnonymizedScope::class);
    }
}
