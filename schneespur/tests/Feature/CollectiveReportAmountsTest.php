<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Setting;
use App\Services\PdfReportService;
use App\Services\Pricing\FlatRateLine;
use App\Services\Pricing\RevenueSummary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * Der Sammel-Nachweis geht an den Kunden. Beträge darin sind eine
 * Entscheidung des Betriebs, keine Folge des Updates.
 */
class CollectiveReportAmountsTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

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

    private function customerWithFlatRate(): Customer
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        CustomerObject::create([
            'customer_id' => $customer->id, 'name' => 'Wohnanlage', 'price_amount_cents' => 24000, 'price_unit' => 'monthly',
        ]);

        return $customer;
    }

    private function revenueFor(Customer $customer): ?RevenueSummary
    {
        return app(PdfReportService::class)->revenueFor($customer, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));
    }

    private function render(Customer $customer, ?RevenueSummary $revenue): string
    {
        return view('pdf.customer-report', [
            'customer' => $customer->load('objects'),
            'jobs' => collect(),
            'jobData' => [],
            'from' => Carbon::parse('2026-01-01'),
            'to' => Carbon::parse('2026-01-31'),
            'coverData' => [],
            'revenue' => $revenue,
        ])->render();
    }

    public function test_amounts_are_off_until_the_business_switches_them_on(): void
    {
        $this->assertNull($this->revenueFor($this->customerWithFlatRate()));
    }

    public function test_switched_on_the_report_gets_a_summary(): void
    {
        Setting::set('report_show_amounts', true, 'bool');

        $summary = $this->revenueFor($this->customerWithFlatRate());

        $this->assertNotNull($summary);
        $this->assertSame(24000, $summary->totalCents());
    }

    public function test_switched_on_but_nothing_to_show_yields_no_block(): void
    {
        Setting::set('report_show_amounts', true, 'bool');

        $this->assertNull($this->revenueFor(Customer::create(['name' => 'Ohne Preise'])));
    }

    public function test_the_view_prints_the_block_when_it_is_given_a_summary(): void
    {
        $customer = $this->customerWithFlatRate();
        $summary = new RevenueSummary(17000, 2, 0, [new FlatRateLine('Wohnanlage', null, 'monthly', 24000, 1)]);

        $html = $this->render($customer, $summary);

        $this->assertStringContainsString(__('job.pdf_amounts_title'), $html);
        $this->assertStringContainsString(format_money(17000), $html);
        $this->assertStringContainsString(format_money(24000), $html);
        $this->assertStringContainsString(format_money(41000), $html);
    }

    public function test_the_view_prints_no_amount_without_a_summary(): void
    {
        $html = $this->render($this->customerWithFlatRate(), null);

        $this->assertStringNotContainsString(__('job.pdf_amounts_title'), $html);
        $this->assertStringNotContainsString(format_money(24000), $html);
    }

    /**
     * Module und ältere Aufrufer rendern die View ohne die neue Variable.
     */
    public function test_the_view_still_renders_when_the_variable_is_missing_entirely(): void
    {
        $html = view('pdf.customer-report', [
            'customer' => $this->customerWithFlatRate()->load('objects'),
            'jobs' => collect(),
            'jobData' => [],
            'from' => Carbon::parse('2026-01-01'),
            'to' => Carbon::parse('2026-01-31'),
            'coverData' => [],
        ])->render();

        $this->assertStringNotContainsString(__('job.pdf_amounts_title'), $html);
    }

    public function test_the_switch_is_saved_from_the_company_settings(): void
    {
        $payload = [
            'company_name' => 'Winterdienst Bergblick',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'de',
            'display_timezone' => 'Europe/Berlin',
            'billing_increment_minutes' => 1,
            'report_show_amounts' => '1',
        ];

        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.company.update'), $payload)->assertSessionHasNoErrors();
        $this->assertTrue((bool) Setting::get('report_show_amounts'));

        unset($payload['report_show_amounts']);
        $payload['report_show_amounts_present'] = '1';

        $this->actingAs($admin)->post(route('admin.settings.company.update'), $payload)->assertSessionHasNoErrors();
        $this->assertFalse((bool) Setting::get('report_show_amounts'));
    }
}
