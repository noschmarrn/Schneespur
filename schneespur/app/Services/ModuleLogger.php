<?php

namespace App\Services;

use App\Models\ModLog;

class ModuleLogger
{
    public function log(string $slug, string $level, string $message, array $context = []): ModLog
    {
        return ModLog::create([
            'module_slug' => $slug,
            'level' => $level,
            'message' => $message,
            'context' => ! empty($context) ? $context : null,
            'created_at' => now(),
        ]);
    }

    public function info(string $slug, string $message, array $context = []): ModLog
    {
        return $this->log($slug, 'info', $message, $context);
    }

    public function warning(string $slug, string $message, array $context = []): ModLog
    {
        return $this->log($slug, 'warning', $message, $context);
    }

    public function error(string $slug, string $message, array $context = []): ModLog
    {
        return $this->log($slug, 'error', $message, $context);
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
