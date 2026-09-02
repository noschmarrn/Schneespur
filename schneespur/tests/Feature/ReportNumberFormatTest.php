<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportNumberFormatTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_the_formatter_honours_the_configured_number_format(): void
    {
        Setting::set('number_format', 'dot_comma');
        app()->forgetInstance(Formatter::class);

        $this->assertSame('1,8', app(Formatter::class)->number(1.8, 1));

        Setting::set('number_format', 'comma_dot');
        app()->forgetInstance(Formatter::class);

        $this->assertSame('1.8', app(Formatter::class)->number(1.8, 1));
    }

    public function test_the_portal_dashboard_does_not_print_a_raw_number_format(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Portal/PortalDashboardController.php'));

        $this->assertStringNotContainsString('number_format(', $source);
    }

    public function test_the_customer_report_does_not_round_without_formatting(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/CustomerReportController.php'));

        $this->assertStringNotContainsString('round($totalJobs / $weeks', $source);
    }
}
