<?php

namespace App\Services\Extension;

use App\ValueObjects\JobTypeValue;

class JobTypeRegistry extends ExtensionRegistry
{
    public function registerType(string $value, string $labelKey, int $order = 100, ?string $module = null): void
    {
        $this->register($value, [
            'value' => $value,
            'label_key' => $labelKey,
            'order' => $order,
            'module' => $module,
        ]);
    }

    public function hasType(string $value): bool
    {
        return $this->has($value);
    }

    /** @return string[] */
    public function values(): array
    {
        return array_map(fn (array $e) => $e['value'], $this->ordered());
    }

    /** @return JobTypeValue[] */
    public function types(): array
    {
        return array_map(fn (array $e) => new JobTypeValue($e['value']), $this->ordered());
    }

    public function label(string $value): string
    {
        $entry = $this->items[$value] ?? null;

        if ($entry === null) {
            return $value;
        }

        return __($entry['label_key']);
    }

    /** @return array<int, array{value: string, label_key: string, order: int, module: string|null}> */
    private function ordered(): array
    {
        $entries = array_values($this->items);
        usort($entries, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $entries;
    }
}
