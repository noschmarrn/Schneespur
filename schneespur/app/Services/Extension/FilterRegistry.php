<?php

namespace App\Services\Extension;

use Illuminate\Support\Facades\Log;

class FilterRegistry
{
    private array $hooks = [];

    private int $insertionCounter = 0;

    public function register(string $hook, callable $callback, int $priority = 100): void
    {
        $this->hooks[$hook][] = [$priority, $this->insertionCounter++, $callback];
    }

    public function apply(string $hook, mixed $value, mixed ...$context): mixed
    {
        if (empty($this->hooks[$hook])) {
            return $value;
        }

        $callbacks = $this->hooks[$hook];
        usort($callbacks, fn (array $a, array $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        foreach ($callbacks as $entry) {
            $previousValue = $value;
            try {
                $value = $entry[2]($value, ...$context);
            } catch (\Throwable $e) {
                Log::warning('FilterRegistry: callback failed', [
                    'hook' => $hook,
                    'index' => $entry[1],
                    'error' => $e->getMessage(),
                ]);
                $value = $previousValue;
            }
        }

        return $value;
    }
}
