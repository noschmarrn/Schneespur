<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\CustomerObjectPrice;
use App\Models\Setting;
use App\Models\User;
use App\Services\JobLifecycleService;
use App\Services\Pricing\PeriodRevenue;
use App\Services\Pricing\RevenueSummary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PeriodRevenueTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?User $driver = null;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('season_from', '11-01');
        Setting::set('season_to', '03-31');
    }

    private function driver(): User
    {
        return $this->driver ??= User::create([
            'name' => 'Driver',
            'email' => 'revenue-driver@test.local',
            'password' => Hash::make('password'),
        ]);
    }

    private function object(Customer $customer, array $overrides = []): CustomerObject
    {
        return CustomerObject::create(array_merge([
            'customer_id' => $customer->id,
            'name' => 'Wohnanlage',
        ], $overrides));
    }

    private function job(CustomerObject $object, string $startedAt, int $minutes = 60, string $type = 'raumen'): void
    {
        $start = Carbon::parse($startedAt);

        app(JobLifecycleService::class)->createManualJob(
            driver: $this->driver(),
            customerObject: $object,
            type: $type,
            startedAt: $start,
            endedAt: $start->copy()->addMinutes($minutes),
        );
    }

    private function revenue(Customer $customer, string $from, string $to, ?CustomerObject $object = null): RevenueSummary
    {
        return app(PeriodRevenue::class)->forCustomer($customer, Carbon::parse($from), Carbon::parse($to), $object);
    }

    public function test_frozen_job_amounts_are_summed(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = $this->object($customer, ['price_amount_cents' => 8500, 'price_unit' => 'per_job']);

        $this->job($object, '2026-01-10 06:00:00');
        $this->job($object, '2026-01-20 06:00:00');
        $this->job($object, '2026-02-02 06:00:00'); // außerhalb

        $summary = $this->revenue($customer, '2026-01-01', '2026-01-31');

        $this->assertSame(17000, $summary->jobTotalCents);
        $this->assertSame(2, $summary->pricedJobCount);
        $this->assertSame(0, $summary->jobsWithoutAmount);
        $this->assertSame(17000, $summary->totalCents());
    }

    public function test_the_last_day_of_the_period_is_included(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = $this->object($customer, ['price_amount_cents' => 8500, 'price_unit' => 'per_job']);

        $this->job($object, '2026-01-31 22:00:00');

        $this->assertSame(8500, $this->revenue($customer, '2026-01-01', '2026-01-31')->jobTotalCents);
    }

    /**
     * Review Focus 1: ein Einsatz ohne jeden Preis wird gezählt und benannt,
     * nicht still als 0,00 in die Summe gelegt.
     */
    public function test_jobs_without_any_price_are_counted_not_summed_as_zero(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $priced = $this->object($customer, ['price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        $unpriced = $this->object($customer, ['name' => 'Ohne Preis']);

        $this->job($priced, '2026-01-10 06:00:00');
        $this->job($unpriced, '2026-01-11 06:00:00');

        $summary = $this->revenue($customer, '2026-01-01', '2026-01-31');

        $this->assertSame(8500, $summary->jobTotalCents);
        $this->assertSame(1, $summary->pricedJobCount);
        $this->assertSame(1, $summary->jobsWithoutAmount);
    }

    public function test_a_monthly_flat_rate_counts_once_per_touched_month(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $object = $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);
        $this->job($object, '2026-01-10 06:00:00');

        $summary = $this->revenue($customer, '2026-01-15', '2026-03-10');

        $this->assertCount(1, $summary->flatRateLines);
        $line = $summary->flatRateLines[0];
        $this->assertSame('monthly', $line->unit);
        $this->assertSame(3, $line->count);
        $this->assertSame(72000, $line->totalCents());
        $this->assertNull($line->jobTypeLabel);
        $this->assertSame(72000, $summary->totalCents());
        // Ein Einsatz unter einer Pauschale hat zu Recht keinen eigenen Betrag.
        $this->assertSame(0, $summary->jobsWithoutAmount);
    }

    /**
     * Review Focus 3: über den Jahreswechsel. Dezember und Januar je einmal,
     * die Saison einmal — nicht zweimal, obwohl zwei Kalenderjahre berührt
     * sind.
     */
    public function test_flat_rates_across_new_year(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $object = $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 145000, 'unit' => 'seasonal',
        ]);

        $summary = $this->revenue($customer, '2025-12-15', '2026-01-15');

        $byUnit = collect($summary->flatRateLines)->keyBy('unit');

        $this->assertSame(2, $byUnit['monthly']->count);
        $this->assertSame(1, $byUnit['seasonal']->count);
        $this->assertNotNull($byUnit['seasonal']->jobTypeLabel);
        $this->assertSame(48000 + 145000, $summary->flatRateTotalCents());
    }

    public function test_a_month_outside_the_season_carries_no_monthly_flat_rate(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);

        // März liegt in der Saison, April und Mai nicht.
        $summary = $this->revenue($customer, '2026-03-01', '2026-05-31');

        $this->assertSame(1, $summary->flatRateLines[0]->count);
    }

    public function test_a_period_wholly_outside_the_season_has_no_flat_rate_line(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);

        $summary = $this->revenue($customer, '2026-06-01', '2026-08-31');

        $this->assertSame([], $summary->flatRateLines);
        $this->assertFalse($summary->hasAnything());
    }

    public function test_a_flat_rate_is_owed_even_without_a_single_job(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $this->object($customer, ['price_amount_cents' => 145000, 'price_unit' => 'seasonal']);

        $summary = $this->revenue($customer, '2026-01-01', '2026-01-31');

        $this->assertSame(145000, $summary->totalCents());
        $this->assertTrue($summary->hasAnything());
    }

    public function test_job_amounts_and_flat_rates_add_up_but_stay_separate(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $object = $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 4000, 'unit' => 'per_job',
        ]);

        $this->job($object, '2026-01-10 06:00:00', 30, 'streuen');

        $summary = $this->revenue($customer, '2026-01-01', '2026-01-31');

        $this->assertSame(4000, $summary->jobTotalCents);
        $this->assertSame(24000, $summary->flatRateTotalCents());
        $this->assertSame(28000, $summary->totalCents());
    }

    public function test_the_object_filter_limits_jobs_and_flat_rates(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $a = $this->object($customer, ['name' => 'A', 'price_amount_cents' => 24000, 'price_unit' => 'monthly']);
        $b = $this->object($customer, ['name' => 'B', 'price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        $this->job($b, '2026-01-10 06:00:00');

        $summary = $this->revenue($customer, '2026-01-01', '2026-01-31', $a);

        $this->assertSame(0, $summary->jobTotalCents);
        $this->assertSame(24000, $summary->totalCents());
    }

    public function test_another_customers_jobs_never_leak_in(): void
    {
        $mine = Customer::create(['name' => 'Meiner']);
        $other = Customer::create(['name' => 'Fremder']);
        $this->job($this->object($other, ['price_amount_cents' => 8500, 'price_unit' => 'per_job']), '2026-01-10 06:00:00');

        $this->assertFalse($this->revenue($mine, '2026-01-01', '2026-01-31')->hasAnything());
    }

    public function test_an_inverted_period_yields_an_empty_summary(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $object = $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 145000, 'unit' => 'seasonal',
        ]);
        $this->job($this->object($customer, ['name' => 'B', 'price_amount_cents' => 8500, 'price_unit' => 'per_job']), '2026-01-10 06:00:00');

        $summary = $this->revenue($customer, '2026-03-01', '2026-01-01');

        $this->assertFalse($summary->hasAnything());
        $this->assertSame(0, $summary->totalCents());
        $this->assertSame([], $summary->flatRateLines);
        $this->assertSame(0, $summary->jobsWithoutAmount);
    }

    public function test_a_monthly_flat_rate_counts_every_month_from_a_month_end_start(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $this->object($customer, ['price_amount_cents' => 24000, 'price_unit' => 'monthly']);

        $summary = $this->revenue($customer, '2026-01-31', '2026-03-01');

        $this->assertSame(3, $summary->flatRateLines[0]->count);
    }
}
