<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * Exports and PDFs leave the screen but not the operator: they land in the
 * same person's Excel and the same customer's letterbox, so they follow the
 * configured format instead of a hardcoded German one.
 */
class FormattedExportTest extends TestCase
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

    /**
     * The CSV lands in the same operator's Excel, which parses by system
     * locale. An ISO date in a US Excel becomes text; a German decimal comma
     * becomes a thousands separator. So the export follows the screen.
     */
    public function test_the_csv_export_follows_the_display_format(): void
    {
        $admin = $this->admin();
        $this->job($admin);

        $response = $this->actingAs($admin)->get('/admin/exports/csv/download?'.http_build_query([
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'variant' => 'all',
        ]));

        $response->assertStatus(200);
        $csv = $response->getContent();

        $this->assertStringContainsString('08/06/2026', $csv);
        $this->assertStringNotContainsString('06.08.2026', $csv);
    }

    /** The times in the same row follow the 12h setting, not a fixed H:i. */
    public function test_the_csv_export_prints_twelve_hour_times(): void
    {
        $admin = $this->admin();
        $this->job($admin);

        $csv = $this->actingAs($admin)->get('/admin/exports/csv/download?'.http_build_query([
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'variant' => 'all',
        ]))->getContent();

        $this->assertStringContainsString('AM', $csv);
    }

    /**
     * The download filename is a machine format and stays untouched — a
     * slash in mdy_slash would be a path separator, not a date.
     */
    public function test_the_export_filename_stays_machine_readable(): void
    {
        $admin = $this->admin();
        $this->job($admin);

        $response = $this->actingAs($admin)->get('/admin/exports/csv/download?'.http_build_query([
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'variant' => 'all',
        ]));

        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="zeiterfassung-all-2026-08-01-2026-08-31.csv"'
        );
    }

    /**
     * dompdf output is compressed binary, so assert on the view that feeds it.
     * PdfReportService::generateJobReport() passes exactly these three keys.
     */
    public function test_the_job_report_view_follows_the_display_format(): void
    {
        $admin = $this->admin();
        $job = $this->job($admin);

        $html = view('pdf.job-report', [
            'job' => $job->fresh()->load(['customer', 'customerObject', 'user', 'gpsPoints', 'weatherSnapshots', 'jobPhotos']),
            'svgTrack' => null,
            'gpsTableData' => ['points' => collect(), 'total' => 0, 'sampled' => false],
        ])->render();

        $this->assertStringContainsString('08/06/2026', $html);
        $this->assertStringNotContainsString('06.08.2026', $html);
    }
}
