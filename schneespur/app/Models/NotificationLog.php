<?php

namespace App\Models;

use Database\Factories\NotificationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    /** @use HasFactory<NotificationLogFactory> */
    use HasFactory;

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'channel',
        'type',
        'recipient',
        'status',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
