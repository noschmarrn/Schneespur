<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The config bridge repaired Formatter::formatMoment() and
 * Job::localStartedAt() for everything that goes through them. These are the
 * places that go through neither and therefore still showed UTC.
 *
 * Every case here straddles local midnight on purpose: that is where a wrong
 * zone stops being a cosmetic hour and becomes the wrong day.
 */
class TimezoneDisplaySweepTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function useZone(string $zone): void
    {
        Setting::set('display_timezone', $zone);
        Timezones::apply();
    }

    /**
     * 2026-01-15 03:00 UTC is 2026-01-14 21:00 in Chicago — a different day.
     */
    private function nightJob(string $startedAt = '2026-01-15 03:00:00', string $endedAt = '2026-01-15 04:00:00'): Job
    {
        $suffix = uniqid();

        $customer = Customer::create(['name' => 'Kunde', 'email' => "k{$suffix}@test.local"]);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Wohnanlage Ludwigsplatz',
        ]);
        $driver = User::create([
            'name' => 'Fahrer',
            'email' => "d{$suffix}@test.local",
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        // service_jobs.work_shift_id and .customer_id are both NOT NULL — a
        // job cannot be created without a shift to hang off.
        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'is_manual' => true,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * The mail body has used localStartedAt() all along; the subject did not.
     * On a night job the two named different days in the same message.
     */
    public function test_the_mail_subject_uses_the_same_day_as_the_mail_body(): void
    {
        $this->useZone('America/Chicago');

        $job = $this->nightJob();

        $expected = $job->localStartedAt()->format(__('notification.date_format'));
        $subject = (new \App\Mail\JobCompletedMail($job, weatherAvailable: false))->envelope()->subject;

        $this->assertStringContainsString($expected, $subject);
        $this->assertStringNotContainsString(
            $job->started_at->utc()->format(__('notification.date_format')),
            $subject
        );
    }

    /**
     * The date inside the PDF goes through the Formatter and converts. The
     * filename did not, so a job just after local midnight produced a file
     * named after the previous day — for the recipient who has to file it.
     */
    public function test_the_pdf_filename_uses_the_local_day(): void
    {
        $this->useZone('America/Chicago');

        $filename = app(\App\Services\PdfReportService::class)->jobReportFilename($this->nightJob());

        $this->assertStringContainsString('2026-01-14', $filename);
        $this->assertStringNotContainsString('2026-01-15', $filename);
    }

    /**
     * Retention groups jobs by calendar month. In UTC a job driven on New
     * Year's Eve after 18:00 local falls into the next year entirely.
     */
    public function test_retention_groups_by_the_local_month(): void
    {
        $this->useZone('America/Chicago');

        // 2026-02-01 03:00 UTC is 2026-01-31 21:00 in Chicago: January.
        $job = $this->nightJob('2026-02-01 02:00:00', '2026-02-01 03:00:00');

        $this->assertSame('2026-01', $job->localEndedAt()->format('Y-m'));
        $this->assertSame('2026-02', $job->ended_at->utc()->format('Y-m'), 'sanity: stored value really is February in UTC');
    }

    /**
     * The same defect in summer, when the offset is a different size. The spec
     * asks for winter and summer on every timezone-sensitive path.
     * Chicago is UTC-5 in July: 2026-07-15 03:00 UTC is 2026-07-14 22:00.
     */
    public function test_the_conversion_is_right_in_summer_too(): void
    {
        $this->useZone('America/Chicago');

        $job = $this->nightJob('2026-07-15 03:00:00', '2026-07-15 04:00:00');

        $this->assertSame('2026-07-14', $job->localStartedAt()->format('Y-m-d'));
        $this->assertSame('22:00', $job->localStartedAt()->format('H:i'));
    }
}
