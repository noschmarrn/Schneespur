<?php

namespace App\Services\Diagnostic;

interface DiagnosticReporterInterface
{
    /**
     * Report a diagnostic event to this reporter.
     *
     * @param  string  $type  Event type, e.g. 'exception', 'cron_failed', 'module_boot_failed'
     * @param  array  $payload  Sanitized event data
     * @param  array  $context  Additional context (route, user role, schneespur version, etc.)
     */
    public function report(string $type, array $payload = [], array $context = []): void;

    public function isEnabled(): bool;

    /**
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function testConnection(): array;
}
