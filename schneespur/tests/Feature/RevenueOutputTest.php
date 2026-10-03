<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Setting;
use App\Models\User;
use App\Services\CsvExportService;
use App\Services\Format\Formatter;
use App\Services\JobLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * Preise konnten hinterlegt werden und tauchten in keiner Auswertung auf.
 * Geprüft wird die gerenderte Seite und die erzeugte Datei.
 */
class RevenueOutputTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

    private ?User $outputDriver = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFormatFixtures();

        Setting::set('season_from', '11-01');
        Setting::set('season_to', '03-31');
    }

    protected function tearDown(): void
    {
        $this->tearDownFormatFixtures();
        parent::tearDown();
    }

    private function outputDriver(): User
    {
        return $this->outputDriver ??= User::create([
            'name' => 'Driver',
            'email' => 'output-driver@test.local',
            'password' => Hash::make('password'),
        ]);
    }

    private function finishedJob(CustomerObject $object, string $startedAt, int $minutes = 60): void
    {
        $start = Carbon::parse($startedAt);

        app(JobLifecycleService::class)->createManualJob(
            driver: $this->outputDriver(),
            customerObject: $object,
            type: 'raumen',
            startedAt: $start,
            endedAt: $start->copy()->addMinutes($minutes),
        );
    }

    private function report(Customer $customer)
    {
        return $this->actingAs($this->admin())->get(route('admin.overview.customer-report', [
            'customer' => $customer->id,
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));
    }

    public function test_the_customer_report_shows_the_amount_per_job_and_the_total(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id, 'name' => 'Parkplatz', 'price_amount_cents' => 8500, 'price_unit' => 'per_job',
        ]);
        $this->finishedJob($object, '2026-01-10 06:00:00');
        $this->finishedJob($object, '2026-01-20 06:00:00');

        $response = $this->report($customer);

        $response->assertOk();
        $response->assertSee(__('overview.report_col_amount'));
        $response->assertSee(format_money(8500));
        $response->assertSee(format_money(17000));
    }

    public function test_flat_rates_appear_as_their_own_lines(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id, 'name' => 'Wohnanlage', 'price_amount_cents' => 24000, 'price_unit' => 'monthly',
        ]);
        $this->finishedJob($object, '2026-01-10 06:00:00');

        $response = $this->report($customer);

        $response->assertSee(__('customer_object.price_unit_monthly'));
        $response->assertSee(format_money(24000));
        $response->assertSee(__('overview.revenue_total'));
    }

    /**
     * Review Focus 1.
     */
    public function test_a_job_without_a_price_shows_a_dash_and_is_named_never_zero(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Ohne Preis']);
        $this->finishedJob($object, '2026-01-10 06:00:00');

        $response = $this->report($customer);

        $response->assertSee(trans_choice('overview.revenue_jobs_without_amount', 1, ['count' => 1]));
        $response->assertDontSee(format_money(0));
    }

    public function test_a_flat_rate_is_shown_even_when_the_period_has_no_jobs(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        CustomerObject::create([
            'customer_id' => $customer->id, 'name' => 'Wohnanlage', 'price_amount_cents' => 24000, 'price_unit' => 'monthly',
        ]);

        $this->report($customer)
            ->assertOk()
            ->assertSee(__('overview.revenue_title'))
            ->assertSee(format_money(24000));
    }

    private function csvLines(): array
    {
        return explode("\n", trim(app(CsvExportService::class)->buildCsv('all', null, null)));
    }

    public function test_the_csv_carries_amount_unit_and_rate_at_the_end(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id, 'name' => 'Parkplatz', 'price_amount_cents' => 6000, 'price_unit' => 'per_hour',
        ]);
        $this->finishedJob($object, '2026-01-10 06:00:00', 90);

        [$header, $row] = $this->csvLines();

        $this->assertStringEndsWith(
            implode(';', [__('export.csv_col_amount'), __('export.csv_col_price_unit'), __('export.csv_col_price_rate')]),
            trim($header),
        );
        // 90 Minuten zu 60,00 je Stunde, minutengenau: 90,00. Ohne Währungszeichen. League\Csv quotiert nur
        // die Einheit (Leerzeichen), nicht die Zahlen.
        $this->assertStringEndsWith(
            implode(';', ['90,00', '"'.__('customer_object.price_unit_per_hour').'"', '60,00']),
            trim($row),
        );
    }

    public function test_the_csv_follows_the_number_format_of_the_business(): void
    {
        Setting::set('number_format', 'comma_dot');
        Setting::set('csv_delimiter', ',');
        app()->forgetInstance(Formatter::class);

        $customer = Customer::create(['name' => 'Summit']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id, 'name' => 'Lot', 'price_amount_cents' => 123456, 'price_unit' => 'per_job',
        ]);
        $this->finishedJob($object, '2026-01-10 06:00:00');

        [, $row] = $this->csvLines();

        $this->assertStringContainsString('"1,234.56"', $row);
    }

    public function test_a_job_without_a_price_leaves_the_three_cells_empty(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Ohne Preis']);
        $this->finishedJob($object, '2026-01-10 06:00:00');

        [, $row] = $this->csvLines();

        $this->assertStringEndsWith(';;;', trim($row));
    }
}
