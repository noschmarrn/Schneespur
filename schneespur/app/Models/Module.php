<?php

namespace App\Models;

use App\Services\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'slug',
        'version',
        'enabled',
        'manifest_json',
        'signature_status',
        'trust_level',
        'installed_at',
    ];

    /**
     * The ModuleManager caches the enable state once per boot (M018/S03).
     * Writers go straight through this model — AdminModuleController updates
     * `enabled` directly rather than via ModuleManager::enable() — so the cache
     * is invalidated here instead of at every call site, where the next one
     * would forget.
     */
    protected static function booted(): void
    {
        $forget = fn () => app(ModuleManager::class)->refreshPersistedState();

        static::saved($forget);
        static::deleted($forget);
    }

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

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ModuleApiToken::class, 'module_slug', 'slug');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ModLog::class, 'module_slug', 'slug');
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
