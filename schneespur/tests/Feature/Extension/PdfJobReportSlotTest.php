<?php

namespace Tests\Feature\Extension;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Extension\FilterRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PdfJobReportSlotTest extends TestCase
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

    private function makeJob(): Job
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'ACME']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Objekt 1']);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
        ]);

        $job = Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
        ]);

        return $job->load(['customer', 'customerObject', 'user', 'vehicle', 'gpsPoints', 'weatherSnapshots', 'jobPhotos']);
    }

    private function renderJobReport(Job $job): string
    {
        return View::make('pdf.job-report', [
            'job' => $job,
            'svgTrack' => null,
            'gpsTableData' => ['points' => collect(), 'total' => 0, 'sampled' => false],
        ])->render();
    }

    public function test_job_end_slot_injects_module_html(): void
    {
        $job = $this->makeJob();

        $this->app->make(FilterRegistry::class)->register(
            'schneespur.pdf.job.end',
            function (array $sections, Job $j): array {
                $sections[] = "<div>JOBEND_{$j->id}</div>";

                return $sections;
            }
        );

        $html = $this->renderJobReport($job);

        $this->assertStringContainsString("JOBEND_{$job->id}", $html);
    }

    public function test_after_details_slot_injects_module_html(): void
    {
        $job = $this->makeJob();

        $this->app->make(FilterRegistry::class)->register(
            'schneespur.pdf.job.after_details',
            fn (array $s, Job $j): array => [...$s, "<div>AFTERDETAILS_{$j->id}</div>"]
        );

        $html = $this->renderJobReport($job);

        $this->assertStringContainsString("AFTERDETAILS_{$job->id}", $html);
    }

    public function test_renders_cleanly_with_no_filters(): void
    {
        $job = $this->makeJob();

        $html = $this->renderJobReport($job);

        $this->assertStringNotContainsString('pdfExtensionSlot', $html);
        $this->assertStringContainsString('ACME', $html);
    }
}
