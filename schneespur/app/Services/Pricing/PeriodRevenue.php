<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Services\Extension\JobTypeRegistry;
use App\Services\SeasonService;
use Carbon\Carbon;

/**
 * Die Auswertung eines Zeitraums für einen Kunden.
 *
 * Einsatzbeträge kommen aus den beim Abschluß festgeschriebenen Spalten und
 * werden nur summiert. Pauschalen hängen an keinem Einsatz und werden aus den
 * HEUTIGEN Preisregeln gerechnet — monatlich je berührtem Kalendermonat in
 * der Saison, saisonal je berührter Saison, unabhängig davon, ob es Einsätze
 * gab.
 */
class PeriodRevenue
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly JobTypeRegistry $jobTypes,
    ) {}

    public function forCustomer(Customer $customer, Carbon $from, Carbon $to, ?CustomerObject $object = null): RevenueSummary
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $jobs = Job::query()
            ->where('customer_id', $customer->id)
            ->when($object, fn ($q) => $q->where('customer_object_id', $object->id))
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to->copy()->addDay())
            ->get(['id', 'price_amount_cents', 'price_unit']);

        $priced = $jobs->whereNotNull('price_amount_cents');

        return new RevenueSummary(
            jobTotalCents: (int) $priced->sum('price_amount_cents'),
            pricedJobCount: $priced->count(),
            jobsWithoutAmount: $jobs->whereNull('price_unit')->count(),
            flatRateLines: $this->flatRateLines($customer, $object, $from, $to),
        );
    }

    /**
     * @return FlatRateLine[]
     */
    private function flatRateLines(Customer $customer, ?CustomerObject $only, Carbon $from, Carbon $to): array
    {
        $counts = [
            'monthly' => $this->monthsInSeason($from, $to),
            'seasonal' => count($this->seasons->seasonsTouching($from, $to)),
        ];

        $objects = $only !== null
            ? collect([$only->loadMissing('prices')])
            : $customer->objects()->with('prices')->orderBy('name')->get();

        $lines = [];

        foreach ($objects as $object) {
            if ($object->price_amount_cents !== null && in_array($object->price_unit, JobPricing::FLAT_UNITS, true)) {
                $lines[] = [$object->name, null, $object->price_unit, $object->price_amount_cents];
            }

            foreach ($object->prices as $row) {
                if (in_array($row->unit, JobPricing::FLAT_UNITS, true)) {
                    $lines[] = [$object->name, $this->jobTypes->label($row->job_type), $row->unit, $row->amount_cents];
                }
            }
        }

        $result = [];

        foreach ($lines as [$objectName, $jobTypeLabel, $unit, $rateCents]) {
            if ($counts[$unit] === 0) {
                continue; // der Zeitraum liegt außerhalb jeder Saison
            }

            $result[] = new FlatRateLine($objectName, $jobTypeLabel, $unit, $rateCents, $counts[$unit]);
        }

        return $result;
    }

    /**
     * Kalendermonate, die der Zeitraum berührt und die mindestens einen Tag
     * in einer Saison haben. Ein angeschnittener Monat zählt voll.
     */
    private function monthsInSeason(Carbon $from, Carbon $to): int
    {
        $seasons = $this->seasons->seasonsTouching($from->copy()->startOfMonth(), $to->copy()->endOfMonth());

        $count = 0;

        for ($month = $from->copy()->startOfMonth(); $month->lessThanOrEqualTo($to); $month->addMonth()) {
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            foreach ($seasons as $season) {
                if ($season->start->lessThanOrEqualTo($monthEnd) && $season->end->greaterThanOrEqualTo($monthStart)) {
                    $count++;

                    break;
                }
            }
        }

        return $count;
    }
}
