<?php

namespace App\Services\Pricing;

/**
 * Eine Pauschale in einer Zeitraum-Auswertung: Satz mal Anzahl der
 * berührten Monate bzw. Saisons.
 */
final class FlatRateLine
{
    public function __construct(
        public readonly string $objectName,
        /** null: der Grundpreis des Objekts, gilt für jede Einsatzart ohne eigene Zeile */
        public readonly ?string $jobTypeLabel,
        public readonly string $unit,
        public readonly int $rateCents,
        public readonly int $count,
    ) {}

    public function totalCents(): int
    {
        return $this->rateCents * $this->count;
    }
}
