<?php

namespace App\Services\Pricing;

/**
 * Was ein Kunde in einem Zeitraum kostet. Einsatzbeträge und Pauschalen
 * bleiben getrennt: die einen sind der beim Abschluß festgeschriebene Preis,
 * die anderen der heutige Satz.
 */
final class RevenueSummary
{
    /**
     * @param FlatRateLine[] $flatRateLines
     */
    public function __construct(
        public readonly int $jobTotalCents,
        public readonly int $pricedJobCount,
        /** Einsätze, für die beim Abschluß gar kein Preis galt */
        public readonly int $jobsWithoutAmount,
        public readonly array $flatRateLines,
    ) {}

    public function flatRateTotalCents(): int
    {
        return array_sum(array_map(fn (FlatRateLine $line) => $line->totalCents(), $this->flatRateLines));
    }

    public function totalCents(): int
    {
        return $this->jobTotalCents + $this->flatRateTotalCents();
    }

    /** Gibt es überhaupt einen Betrag zu zeigen? */
    public function hasAnything(): bool
    {
        return $this->pricedJobCount > 0 || $this->flatRateLines !== [];
    }
}
