<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

class FormattedOutputTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFormatFixtures();

        foreach ([
            'currency_code' => 'USD',
            'currency_position' => 'before',
            'number_format' => 'comma_dot',
            'date_format' => 'mdy_slash',
            'time_format' => '12h',
            'unit_distance' => 'imperial',
            'unit_temperature' => 'fahrenheit',
            'unit_weight' => 'imperial',
        ] as $key => $value) {
            Setting::set($key, $value);
        }

        app()->forgetInstance(Formatter::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownFormatFixtures();

        parent::tearDown();
    }

    public function test_the_job_detail_page_uses_the_configured_date_format(): void
    {
        $admin = $this->admin();
        $job = $this->job($admin);

        $response = $this->actingAs($admin)->get("/admin/jobs/{$job->id}");

        $response->assertStatus(200);
        $response->assertSee('08/06/2026');
        $response->assertDontSee('06.08.2026');
    }

    /**
     * There is no admin.customers.show route (CustomerController excludes
     * `show`) — the only place the app prints a bare "€" is the customer
     * object price field, on the object's edit form. That is what this
     * test targets instead of the brief's assumed /admin/customers/{id}.
     */
    public function test_the_object_page_prints_dollars(): void
    {
        $admin = $this->admin();
        $job = $this->job($admin);

        $response = $this->actingAs($admin)
            ->get("/admin/customers/{$job->customer_id}/objects/{$job->customer_object_id}/edit");

        $response->assertStatus(200);
        $response->assertDontSee('€');
        $response->assertSee('$');
    }
}
