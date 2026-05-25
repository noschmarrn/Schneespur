<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModLog extends Model
{
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $table = 'mod_logs';

    protected $fillable = [
        'module_slug',
        'level',
        'message',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('mod_logs is insert-only'));
        static::deleting(fn () => throw new \LogicException('mod_logs is insert-only'));
    }

    public function scopeForModule(Builder $query, string $slug): Builder
    {
        return $query->where('module_slug', $slug);
    }

    public function scopeOfLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopeRecent(Builder $query, int $limit = 100): Builder
    {
        return $query->latest('created_at')->limit($limit);
    }
}
