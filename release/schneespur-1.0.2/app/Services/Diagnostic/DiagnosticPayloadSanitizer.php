<?php

namespace App\Services\Diagnostic;

class DiagnosticPayloadSanitizer
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'passwort',
        'secret',
        'token',
        'api_key',
        'apikey',
        'api-key',
        'authorization',
        'auth',
        'cookie',
        'cookies',
        'session',
        'session_id',
        'csrf',
        '_token',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'dsn',
    ];

    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    private const IPV4_PATTERN = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';

    public function sanitize(array $payload): array
    {
        return $this->walkArray($payload);
    }

    public function sanitizeException(\Throwable $e, bool $includeTrace = false): array
    {
        $sanitized = [
            'class' => get_class($e),
            'message' => $this->truncateMessage($e->getMessage()),
            'code' => $e->getCode(),
            'file' => $this->stripBasePath($e->getFile()),
            'line' => $e->getLine(),
        ];

        if ($includeTrace) {
            $sanitized['trace'] = $this->sanitizeTrace($e);
        }

        return $sanitized;
    }

    public function buildContext(): array
    {
        $context = [
            'schneespur_version' => $this->readVersion(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'active_modules' => $this->activeModuleSlugs(),
        ];

        if (app()->runningInConsole()) {
            $context['channel'] = 'cli';
        } else {
            $context['channel'] = 'http';
            $context['route'] = $this->currentRouteWithoutQuery();
            $context['method'] = request()->method();
        }

        return $context;
    }

    private function walkArray(array $data, int $depth = 0): array
    {
        if ($depth > 10) {
            return [self::REDACTED];
        }

        $result = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if ($this->isSensitiveKey($lowerKey)) {
                $result[$key] = self::REDACTED;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->walkArray($value, $depth + 1);
            } elseif (is_string($value)) {
                $result[$key] = $this->sanitizeString($value, $lowerKey);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeString(string $value, string $key): string
    {
        if (in_array($key, ['email', 'e-mail', 'mail', 'e_mail'], true)) {
            return self::REDACTED;
        }

        $value = preg_replace(self::EMAIL_PATTERN, self::REDACTED, $value);
        $value = preg_replace(self::IPV4_PATTERN, self::REDACTED, $value);

        return $value;
    }

    private function truncateMessage(string $message, int $maxLength = 500): string
    {
        $message = preg_replace(self::EMAIL_PATTERN, self::REDACTED, $message);
        $message = preg_replace(self::IPV4_PATTERN, self::REDACTED, $message);

        if (mb_strlen($message) > $maxLength) {
            return mb_substr($message, 0, $maxLength) . '...';
        }

        return $message;
    }

    private function sanitizeTrace(\Throwable $e): array
    {
        $frames = [];

        foreach (array_slice($e->getTrace(), 0, 30) as $frame) {
            $frames[] = [
                'file' => isset($frame['file']) ? $this->stripBasePath($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }

        return $frames;
    }

    private function stripBasePath(string $path): string
    {
        $base = base_path() . '/';

        return str_starts_with($path, $base)
            ? substr($path, strlen($base))
            : $path;
    }

    private function currentRouteWithoutQuery(): ?string
    {
        try {
            $route = request()->route();
            if ($route) {
                return $route->uri();
            }

            return parse_url(request()->url(), PHP_URL_PATH);
        } catch (\Throwable) {
            return null;
        }
    }

    private function readVersion(): string
    {
        try {
            $path = base_path('VERSION');
            if (file_exists($path)) {
                return trim(file_get_contents($path));
            }
        } catch (\Throwable) {
        }

        return 'unknown';
    }

    private function activeModuleSlugs(): array
    {
        try {
            $manager = app(\App\Services\ModuleManager::class);
            $slugs = [];
            foreach ($manager->getAll() as $slug => $manifest) {
                if ($manager->isEnabled($slug)) {
                    $slugs[] = $slug;
                }
            }

            return $slugs;
        } catch (\Throwable) {
            return [];
        }
    }
}
