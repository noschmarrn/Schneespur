<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\PdfReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PdfFilenameTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Since task 4, the first Content-Disposition segment is
     * translator-controlled (job.pdf_filename_proof / _combined). A language
     * pack with a quote, space or non-ASCII character in that key must not
     * reach the header unescaped — PdfReportService is expected to slug it,
     * same as every other segment.
     */
    public function test_a_hostile_translation_does_not_break_out_of_the_filename(): void
    {
        app()->setLocale('de');
        app('translator')->addLines([
            'job.pdf_filename_proof' => 'Bösewicht" evil/../name',
            'job.pdf_filename_combined' => 'Bösewicht" evil/../name',
        ], 'de');

        $customer = Customer::create(['name' => 'Kunde Ä']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Objekt Ö']);
        $driver = User::create([
            'name' => 'Fahrer',
            'email' => 'fahrer@test.local',
            'password' => Hash::make('password'),
        ]);
        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
        $job = Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);

        $service = $this->app->make(PdfReportService::class);

        $from = Carbon::now()->subDay();
        $to = Carbon::now();

        foreach ([
            $service->objectReportFilename($object, $from, $to),
            $service->customerReportFilename($customer, $from, $to),
            $service->jobReportFilename($job),
        ] as $filename) {
            $this->assertStringNotContainsString('"', $filename);
            $this->assertStringNotContainsString(' ', $filename);
            $this->assertStringNotContainsString('/', $filename);
            $this->assertMatchesRegularExpression('/^[a-z0-9.\-]+$/', $filename);
        }
    }

    public function test_the_filename_slug_follows_the_active_language(): void
    {
        app()->setLocale('de');
        $this->assertSame('einsatznachweis', __('job.pdf_filename_proof'));

        app()->setLocale('en');
        $this->assertSame('proof-of-service', __('job.pdf_filename_proof'));
    }

    public function test_the_combined_report_slug_follows_the_active_language(): void
    {
        app()->setLocale('de');
        $this->assertSame('sammel-einsatznachweis', __('job.pdf_filename_combined'));

        app()->setLocale('en');
        $this->assertSame('combined-proof-of-service', __('job.pdf_filename_combined'));
    }
}
