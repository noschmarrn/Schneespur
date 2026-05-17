<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $row = static::where('key', $key)->first();
        } catch (QueryException|\PDOException) {
            return $default;
        }

        if (! $row) {
            return $default;
        }

        return self::coerce($row->value, $row->type);
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = match ($type) {
            'json' => json_encode($value),
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );
    }

    private static function coerce(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'bool' => in_array($value, ['1', 'true', 'yes'], true),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
