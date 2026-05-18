<?php

namespace App\Services\Diagnostic;

use Illuminate\Support\Facades\Log;

class DiagnosticManager
{
    private bool $dispatching = false;

    public function __construct(
        private readonly DiagnosticReporterRegistry $registry,
        private readonly DiagnosticPayloadSanitizer $sanitizer,
    ) {}

    public function report(string $type, array $payload = [], array $context = []): void
    {
        if ($this->dispatching) {
            return;
        }

        $this->dispatching = true;

        try {
            $sanitizedPayload = $this->sanitizer->sanitize($payload);
            $baseContext = $this->sanitizer->buildContext();
            $mergedContext = array_merge($baseContext, $this->sanitizer->sanitize($context));

            foreach ($this->registry->enabledReporters() as $slug => $reporter) {
                try {
                    $reporter->report($type, $sanitizedPayload, $mergedContext);
                } catch (\Throwable $e) {
                    Log::warning('DiagnosticManager: reporter failed', [
                        'reporter' => $slug,
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $this->dispatching = false;
        }
    }

    public function reportException(\Throwable $e, array $context = [], bool $includeTrace = true): void
    {
        $payload = $this->sanitizer->sanitizeException($e, $includeTrace);

        $this->report('exception', $payload, $context);
    }

    public function hasReporters(): bool
    {
        return count($this->registry->all()) > 0;
    }

    public function hasEnabledReporters(): bool
    {
        return count($this->registry->enabledReporters()) > 0;
    }

    /**
     * @return array<string, array{ok: bool, message: string, latency_ms: int}>
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->registry->enabledReporters() as $slug => $reporter) {
            try {
                $results[$slug] = $reporter->testConnection();
            } catch (\Throwable $e) {
                $results[$slug] = [
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'latency_ms' => 0,
                ];
            }
        }

        return $results;
    }
}
