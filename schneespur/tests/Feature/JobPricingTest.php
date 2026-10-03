<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\CustomerObjectPrice;
use App\Models\Setting;
use App\Models\User;
use App\Services\JobLifecycleService;
use App\Services\Pricing\JobPricing;
use App\Services\Pricing\PriceRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

class JobPricingTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFormatFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownFormatFixtures();
        parent::tearDown();
    }

    private function object(array $overrides = []): CustomerObject
    {
        $customer = Customer::create(['name' => 'Autohaus Inn']);

        return CustomerObject::create(array_merge([
            'customer_id' => $customer->id,
            'name' => 'Kundenparkplatz',
        ], $overrides));
    }

    private function driver(): User
    {
        return User::create([
            'name' => 'Driver',
            'email' => 'pricing-driver@test.local',
            'password' => Hash::make('password'),
        ]);
    }

    private function manualJob(CustomerObject $object, int $minutes, string $type = 'raumen')
    {
        $start = Carbon::parse('2026-01-15 06:00:00');

        return app(JobLifecycleService::class)->createManualJob(
            driver: $this->driver(),
            customerObject: $object,
            type: $type,
            startedAt: $start,
            endedAt: $start->copy()->addMinutes($minutes),
        )->fresh();
    }

    // --- resolve -----------------------------------------------------------

    public function test_a_row_for_the_job_type_wins_over_the_base_price(): void
    {
        $object = $this->object(['price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 4000, 'unit' => 'per_job',
        ]);

        $rule = app(JobPricing::class)->resolve($object, 'streuen');

        $this->assertSame(4000, $rule->amountCents);
        $this->assertSame('per_job', $rule->unit);
    }

    public function test_the_base_price_applies_when_no_row_exists_for_the_job_type(): void
    {
        $object = $this->object(['price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 4000, 'unit' => 'per_job',
        ]);

        $rule = app(JobPricing::class)->resolve($object, 'raumen');

        $this->assertSame(8500, $rule->amountCents);
    }

    public function test_no_price_at_all_resolves_to_null(): void
    {
        $this->assertNull(app(JobPricing::class)->resolve($this->object(), 'raumen'));
    }

    public function test_a_base_amount_without_a_unit_is_not_a_price(): void
    {
        $object = $this->object(['price_amount_cents' => 8500, 'price_unit' => null]);

        $this->assertNull(app(JobPricing::class)->resolve($object, 'raumen'));
    }

    // --- amountFor ---------------------------------------------------------

    /**
     * 60,00 je Stunde. Die Grenzen 52 / 60 / 61 Minuten je Taktstufe.
     *
     * @return array<string, array{int, int, int}>
     */
    public static function hourlyCases(): array
    {
        return [
            'minutengenau, 52 min' => [52, 1, 5200],
            'minutengenau, 60 min' => [60, 1, 6000],
            'minutengenau, 61 min' => [61, 1, 6100],
            '15er-Takt, 52 min → 60' => [52, 15, 6000],
            '15er-Takt, 60 min → 60' => [60, 15, 6000],
            '15er-Takt, 61 min → 75' => [61, 15, 7500],
            '30er-Takt, 52 min → 60' => [52, 30, 6000],
            '30er-Takt, 61 min → 90' => [61, 30, 9000],
            '60er-Takt, 52 min → 60' => [52, 60, 6000],
            '60er-Takt, 60 min → 60' => [60, 60, 6000],
            '60er-Takt, 61 min → 120' => [61, 60, 12000],
            'null Minuten kosten nichts' => [0, 15, 0],
        ];
    }

    #[DataProvider('hourlyCases')]
    public function test_the_hourly_amount_rounds_the_duration_up_to_the_increment(int $minutes, int $increment, int $expected): void
    {
        $amount = app(JobPricing::class)->amountFor(new PriceRule(6000, 'per_hour'), $minutes, $increment);

        $this->assertSame($expected, $amount);
    }

    public function test_an_odd_hourly_rate_is_rounded_to_the_cent(): void
    {
        // 45,50 je Stunde, 20 Minuten: 15,1666… → 15,17
        $this->assertSame(1517, app(JobPricing::class)->amountFor(new PriceRule(4550, 'per_hour'), 20, 1));
    }

    public function test_a_per_job_price_ignores_the_duration(): void
    {
        $this->assertSame(8500, app(JobPricing::class)->amountFor(new PriceRule(8500, 'per_job'), 200, 15));
    }

    public function test_a_flat_rate_has_no_amount_per_job(): void
    {
        $pricing = app(JobPricing::class);

        $this->assertNull($pricing->amountFor(new PriceRule(24000, 'monthly'), 60, 1));
        $this->assertNull($pricing->amountFor(new PriceRule(145000, 'seasonal'), 60, 1));
    }

    // --- incrementMinutes --------------------------------------------------

    public function test_the_increment_defaults_to_one_minute(): void
    {
        $this->assertSame(1, app(JobPricing::class)->incrementMinutes());
    }

    public function test_an_unknown_stored_increment_falls_back_to_one_minute(): void
    {
        Setting::set('billing_increment_minutes', 7, 'int');

        $this->assertSame(1, app(JobPricing::class)->incrementMinutes());
    }

    // --- freeze, über den Lifecycle ----------------------------------------

    public function test_a_finished_job_freezes_a_per_job_price(): void
    {
        $job = $this->manualJob($this->object(['price_amount_cents' => 8500, 'price_unit' => 'per_job']), 52);

        $this->assertSame(8500, $job->price_amount_cents);
        $this->assertSame('per_job', $job->price_unit);
        $this->assertSame(8500, $job->price_rate_cents);
        $this->assertNull($job->price_increment_min);
    }

    public function test_a_finished_job_freezes_an_hourly_price_with_its_increment(): void
    {
        Setting::set('billing_increment_minutes', 15, 'int');

        $job = $this->manualJob($this->object(['price_amount_cents' => 6000, 'price_unit' => 'per_hour']), 52);

        $this->assertSame(6000, $job->price_amount_cents);
        $this->assertSame('per_hour', $job->price_unit);
        $this->assertSame(6000, $job->price_rate_cents);
        $this->assertSame(15, $job->price_increment_min);
    }

    public function test_a_job_under_a_flat_rate_records_the_rate_but_no_amount(): void
    {
        $job = $this->manualJob($this->object(['price_amount_cents' => 24000, 'price_unit' => 'monthly']), 52);

        $this->assertNull($job->price_amount_cents);
        $this->assertSame('monthly', $job->price_unit);
        $this->assertSame(24000, $job->price_rate_cents);
    }

    /**
     * Unbekannt ist nicht null Euro. Derselbe Unterschied wie bei der
     * Schneehöhe: wer hier 0 schreibt, behauptet einen kostenlosen Einsatz.
     */
    public function test_a_job_without_any_price_stays_empty_not_zero(): void
    {
        $job = $this->manualJob($this->object(), 52);

        $this->assertNull($job->price_amount_cents);
        $this->assertNull($job->price_unit);
        $this->assertNull($job->price_rate_cents);
        $this->assertNull($job->price_increment_min);
    }

    public function test_a_later_price_change_leaves_the_frozen_amount_alone(): void
    {
        $object = $this->object(['price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        $job = $this->manualJob($object, 52);

        $object->update(['price_amount_cents' => 9900]);

        $this->assertSame(8500, $job->fresh()->price_amount_cents);
    }

    public function test_ending_a_running_job_freezes_the_price_too(): void
    {
        $object = $this->object(['price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        $driver = $this->driver();

        $service = app(JobLifecycleService::class);
        $service->startShift($driver);
        $service->startJob($driver, $object, 'raumen');
        $job = $service->endJob($driver);

        $this->assertSame(8500, $job->fresh()->price_amount_cents);
    }

    public function test_the_row_for_the_jobs_own_type_is_the_one_frozen(): void
    {
        $object = $this->object(['price_amount_cents' => 8500, 'price_unit' => 'per_job']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 4000, 'unit' => 'per_job',
        ]);

        $this->assertSame(4000, $this->manualJob($object, 30, 'streuen')->price_amount_cents);
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Winterdienst Bergblick',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'de',
            'display_timezone' => 'Europe/Berlin',
            'billing_increment_minutes' => 15,
        ], $overrides);
    }

    public function test_the_increment_is_saved_from_the_company_settings(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.company.update'), $this->companyPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(15, app(JobPricing::class)->incrementMinutes());
    }

    public function test_an_increment_outside_the_four_steps_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.company.update'), $this->companyPayload(['billing_increment_minutes' => 20]))
            ->assertSessionHasErrors('billing_increment_minutes');
    }

    public function test_the_company_page_offers_the_increment(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.company'))
            ->assertOk()
            ->assertSee('name="billing_increment_minutes"', false);
    }
}
