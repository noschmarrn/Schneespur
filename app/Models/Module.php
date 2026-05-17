<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'slug',
        'version',
        'enabled',
        'manifest_json',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'manifest_json' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->pickLocalized('name'));
    }

    protected function description(): Attribute
    {
        return Attribute::get(fn () => $this->pickLocalized('description'));
    }

    private function pickLocalized(string $field): ?string
    {
        $manifest = $this->manifest_json;
        if (! is_array($manifest) || ! isset($manifest[$field])) {
            return null;
        }

        $value = $manifest[$field];

        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'de');

        return $value[$locale] ?? $value[$fallback] ?? reset($value) ?: null;
    }
}
