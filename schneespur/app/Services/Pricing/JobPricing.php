<?php

namespace App\Services\Pricing;

use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;

/**
 * Welcher Preis gilt für einen Einsatz, und was kostet er.
 *
 * Der Betrag wird beim Abschluß am Einsatz festgeschrieben (freeze): eine
 * Preiserhöhung im Januar ändert die Dezember-Auswertung nicht. Ein Modul,
 * das einen Betrag braucht, liest die festgeschriebenen Spalten oder fragt
 * diese Klasse — es rechnet nicht selbst.
 */
class JobPricing
{
    public const UNITS = ['per_job', 'per_hour', 'monthly', 'seasonal'];

    public const FLAT_UNITS = ['monthly', 'seasonal'];

    /** Abrechnungstakte in Minuten; 1 heißt minutengenau. */
    public const INCREMENTS = [1, 15, 30, 60];

    /**
     * Die Zeile für genau diese Einsatzart, sonst der Grundpreis am Objekt,
     * sonst null. Ein Betrag ohne Einheit ist kein Preis.
     */
    public function resolve(CustomerObject $object, string $jobType): ?PriceRule
    {
        $row = $object->prices()->where('job_type', $jobType)->first();

        if ($row !== null) {
            return new PriceRule($row->amount_cents, $row->unit);
        }

        if ($object->price_amount_cents === null || ! in_array($object->price_unit, self::UNITS, true)) {
            return null;
        }

        return new PriceRule($object->price_amount_cents, $object->price_unit);
    }

    public function incrementMinutes(): int
    {
        $stored = (int) Setting::get('billing_increment_minutes', 1);

        return in_array($stored, self::INCREMENTS, true) ? $stored : 1;
    }

    /**
     * Der Betrag eines einzelnen Einsatzes in Cent. Pauschalen haben keinen:
     * sie hängen am Zeitraum, nicht am Einsatz (siehe PeriodRevenue).
     *
     * Beim Stundenpreis wird die Dauer auf den Takt AUFgerundet — "je
     * angefangene Viertelstunde" —, der Betrag kaufmännisch auf den Cent.
     */
    public function amountFor(PriceRule $rule, int $minutes, int $incrementMinutes): ?int
    {
        return match ($rule->unit) {
            'per_job' => $rule->amountCents,
            'per_hour' => (int) round(
                $rule->amountCents * $this->billedMinutes($minutes, $incrementMinutes) / 60
            ),
            default => null,
        };
    }

    /**
     * Schreibt Betrag, Einheit, Satz und Takt am Einsatz fest. Ohne Preis
     * bleiben alle vier Spalten leer — leer heißt "unbekannt", nicht 0.
     */
    public function freeze(Job $job): void
    {
        $object = $job->customerObject;
        $rule = $object === null ? null : $this->resolve($object, $job->type->value);

        if ($rule === null || $job->ended_at === null) {
            $job->forceFill([
                'price_amount_cents' => null,
                'price_unit' => null,
                'price_rate_cents' => null,
                'price_increment_min' => null,
            ])->save();

            return;
        }

        $minutes = (int) round($job->started_at->diffInSeconds($job->ended_at) / 60);
        $increment = $rule->unit === 'per_hour' ? $this->incrementMinutes() : null;

        $job->forceFill([
            'price_amount_cents' => $this->amountFor($rule, $minutes, $increment ?? 1),
            'price_unit' => $rule->unit,
            'price_rate_cents' => $rule->amountCents,
            'price_increment_min' => $increment,
        ])->save();
    }

    private function billedMinutes(int $minutes, int $incrementMinutes): int
    {
        $minutes = max(0, $minutes);
        $incrementMinutes = max(1, $incrementMinutes);

        return (int) (ceil($minutes / $incrementMinutes) * $incrementMinutes);
    }
}
