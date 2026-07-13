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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PdfCollectiveReportSlotTest extends TestCase
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

    private function makeJobs(int $count): array
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $customer = Customer::create(['name' => 'ACME']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Objekt 1']);

        $jobs = [];
        for ($i = 0; $i < $count; $i++) {
            $shift = WorkShift::create([
                'user_id' => $driver->id,
                'started_at' => Carbon::now()->subHours($i + 2),
                'ended_at' => Carbon::now()->subHours($i + 1),
            ]);
            $job = Job::create([
                'work_shift_id' => $shift->id,
                'user_id' => $driver->id,
                'customer_id' => $customer->id,
                'customer_object_id' => $object->id,
                'type' => 'raumen',
                'started_at' => Carbon::now()->subHours($i + 2),
                'ended_at' => Carbon::now()->subHours($i + 1),
            ]);
            $jobs[] = $job->load(['customer', 'customerObject', 'user', 'vehicle', 'gpsPoints', 'weatherSnapshots', 'jobPhotos']);
        }

        return [$customer, collect($jobs)];
    }

    private function renderCustomerReport(Customer $customer, Collection $jobs): string
    {
        $jobData = [];
        foreach ($jobs as $job) {
            $jobData[$job->id] = ['svgTrack' => null, 'gpsTableData' => ['points' => collect(), 'total' => 0, 'sampled' => false]];
        }

        $coverData = [
            'totalJobs' => $jobs->count(),
            'totalMinutes' => 60,
            'typeBreakdown' => ['Räumen' => $jobs->count()],
            'weather' => ['hasData' => false, 'minTemp' => null, 'maxTemp' => null, 'topConditions' => [], 'jobsWithoutWeather' => 0],
        ];

        return View::make('pdf.customer-report', [
            'customer' => $customer,
            'customerObject' => null,
            'jobs' => $jobs,
            'jobData' => $jobData,
            'from' => Carbon::now()->subDay(),
            'to' => Carbon::now(),
            'coverData' => $coverData,
        ])->render();
    }

    public function test_per_job_slot_appears_once_per_job(): void
    {
        [$customer, $jobs] = $this->makeJobs(2);

        $this->app->make(FilterRegistry::class)->register(
            'schneespur.pdf.job.end',
            fn (array $s, Job $j): array => [...$s, '<div>PERJOB_MARKER</div>']
        );

        $html = $this->renderCustomerReport($customer, $jobs);

        $this->assertSame(2, substr_count($html, 'PERJOB_MARKER'));
    }

    public function test_cover_end_slot_appears_on_cover(): void
    {
        [$customer, $jobs] = $this->makeJobs(1);

        $this->app->make(FilterRegistry::class)->register(
            'schneespur.pdf.collective.cover_end',
            fn (array $s, Customer $c, Collection $j, $from, $to): array => [...$s, "<div>COVER_{$c->name}_{$j->count()}</div>"]
        );

        $html = $this->renderCustomerReport($customer, $jobs);

        $this->assertStringContainsString('COVER_ACME_1', $html);
    }
}
