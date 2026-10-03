<?php

namespace App\Services\Pricing;

/**
 * Ein Preis, wie er für einen Einsatz gilt: Betrag und Einheit. Woher er
 * stammt — eine Zeile je Einsatzart oder der Grundpreis am Objekt — ist dem
 * Rechner gleich.
 */
final class PriceRule
{
    public function __construct(
        public readonly int $amountCents,
        public readonly string $unit,
    ) {}

    public function isFlatRate(): bool
    {
        return in_array($this->unit, JobPricing::FLAT_UNITS, true);
    }
}
