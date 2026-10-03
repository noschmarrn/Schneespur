<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\JobAudit;
use App\Models\User;
use App\Mail\JobCompletedMail;
use App\Services\JobLifecycleService;
use App\Services\PdfReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Finder\Finder;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * Jede Fahrernotiz erschien bisher wortgleich im Portal und im Nachweis.
 * internal_notes ist das Feld, das der Kunde nie liest — und genau das wird
 * hier festgehalten, nicht nur, daß es gespeichert wird.
 */
class InternalNotesTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

    private const SECRET = 'Kunde schuldet noch die Januar-Rechnung';

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

    private function driver(): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'notes-driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    private function object(): CustomerObject
    {
        $customer = Customer::create(['name' => 'Autohaus Inn']);

        return CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Kundenparkplatz']);
    }

    public function test_ending_a_job_stores_both_notes_separately(): void
    {
        $driver = $this->driver();
        $object = $this->object();

        $service = app(JobLifecycleService::class);
        $service->startShift($driver);
        $service->startJob($driver, $object, 'raumen');

        $this->actingAs($driver)
            ->post(route('driver.job.end'), [
                'notes' => 'Mülltonnenplatz war zugeparkt',
                'internal_notes' => self::SECRET,
            ])
            ->assertSessionHasNoErrors();

        $job = Job::firstOrFail();
        $this->assertSame('Mülltonnenplatz war zugeparkt', $job->notes);
        $this->assertSame(self::SECRET, $job->internal_notes);
    }

    public function test_the_driver_form_warns_that_the_customer_reads_the_first_field(): void
    {
        $this->object();

        $response = $this->actingAs($this->driver())->get(route('driver.home'));

        $response->assertSee(__('driver.dash_notes_customer_hint'));
        $response->assertSee('name="internal_notes"', false);
    }

    public function test_a_manual_job_carries_the_internal_note(): void
    {
        $driver = $this->driver();
        $object = $this->object();

        $job = app(JobLifecycleService::class)->createManualJob(
            driver: $driver,
            customerObject: $object,
            type: 'raumen',
            startedAt: now()->subHours(2),
            endedAt: now()->subHour(),
            notes: 'sichtbar',
            internalNotes: self::SECRET,
        );

        $this->assertSame(self::SECRET, $job->fresh()->internal_notes);
    }

    public function test_the_admin_can_edit_the_internal_note_and_the_change_is_audited(): void
    {
        $driver = $this->driver();
        $job = app(JobLifecycleService::class)->createManualJob(
            driver: $driver,
            customerObject: $this->object(),
            type: 'raumen',
            startedAt: now()->subHours(2),
            endedAt: now()->subHour(),
        );

        $this->actingAs($this->admin())
            ->put(route('admin.jobs.update', $job), ['notes' => 'sichtbar', 'internal_notes' => self::SECRET])
            ->assertSessionHasNoErrors();

        $this->assertSame(self::SECRET, $job->fresh()->internal_notes);

        $audit = JobAudit::where('job_id', $job->id)->latest('id')->firstOrFail();
        $this->assertSame(self::SECRET, $audit->new_values['internal_notes'] ?? null);
    }

    public function test_the_admin_job_page_shows_the_internal_note(): void
    {
        $job = app(JobLifecycleService::class)->createManualJob(
            driver: $this->driver(),
            customerObject: $this->object(),
            type: 'raumen',
            startedAt: now()->subHours(2),
            endedAt: now()->subHour(),
            internalNotes: self::SECRET,
        );

        $this->actingAs($this->admin())
            ->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertSee(self::SECRET);
    }

    public function test_the_portal_never_shows_the_internal_note(): void
    {
        $object = $this->object();
        $customer = $object->customer;
        $customer->forceFill(['portal_enabled' => true, 'password' => Hash::make('password')])->save();

        $job = app(JobLifecycleService::class)->createManualJob(
            driver: $this->driver(),
            customerObject: $object,
            type: 'raumen',
            startedAt: now()->subHours(2),
            endedAt: now()->subHour(),
            notes: 'sichtbar fuer den Kunden',
            internalNotes: self::SECRET,
        );

        $response = $this->actingAs($customer->fresh(), 'customer')->get(route('portal.jobs.show', $job));

        $response->assertOk();
        $response->assertSee('sichtbar fuer den Kunden');
        $response->assertDontSee(self::SECRET);
    }

    /**
     * Kein View, das der Kunde zu sehen bekommt, darf das Feld auch nur
     * nennen. Das ist der Wächter gegen die nächste Änderung, nicht gegen
     * diese.
     */
    public function test_no_customer_facing_view_touches_the_internal_note(): void
    {
        $offenders = [];

        $finder = Finder::create()->files()->name('*.blade.php')->in([
            resource_path('views/portal'),
            resource_path('views/pdf'),
            resource_path('views/mail'),
        ]);

        foreach ($finder as $file) {
            if (str_contains($file->getContents(), 'internal_notes')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertGreaterThan(5, $finder->count(), 'Der Wächter hat kaum Dateien gesehen — stimmen die Pfade noch?');
        $this->assertSame([], $offenders);
    }

    private function finishedJobWithBothNotes(): Job
    {
        $job = app(JobLifecycleService::class)->createManualJob(
            driver: $this->driver(),
            customerObject: $this->object(),
            type: 'raumen',
            startedAt: now()->subHours(2),
            endedAt: now()->subHour(),
            notes: 'Zufahrt war frei, Hof geraeumt',
            internalNotes: self::SECRET,
        );

        return $job->fresh()->load(['customer', 'customerObject', 'user', 'vehicle', 'gpsPoints', 'weatherSnapshots', 'jobPhotos']);
    }

    /**
     * Die Datei-Wächter oben greifen nur auf den Buchstaben "internal_notes".
     * Ein @json($job) oder $job->toArray() in einem Kunden-View käme an ihnen
     * vorbei — deshalb wird hier das gerenderte HTML geprüft.
     */
    public function test_the_rendered_single_job_proof_does_not_contain_the_internal_note(): void
    {
        $job = $this->finishedJobWithBothNotes();

        $html = view('pdf.job-report', [
            'job' => $job,
            'svgTrack' => null,
            'gpsTableData' => app(PdfReportService::class)->sampleGpsPointsForTable($job->gpsPoints),
        ])->render();

        $this->assertStringContainsString('Zufahrt war frei, Hof geraeumt', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
    }

    public function test_the_rendered_collective_report_does_not_contain_the_internal_note(): void
    {
        $job = $this->finishedJobWithBothNotes();
        $service = app(PdfReportService::class);

        // buildCoverData() ist privat; der Test nutzt es trotzdem, statt die
        // Deckblatt-Struktur von Hand nachzubauen.
        $coverData = (new \ReflectionMethod($service, 'buildCoverData'))->invoke($service, collect([$job]));

        $html = view('pdf.customer-report', [
            'customer' => $job->customer->load('objects'),
            'jobs' => collect([$job]),
            'jobData' => [$job->id => [
                'svgTrack' => null,
                'gpsTableData' => $service->sampleGpsPointsForTable($job->gpsPoints),
            ]],
            'from' => Carbon::now()->subDay(),
            'to' => Carbon::now(),
            'coverData' => $coverData,
            'revenue' => null,
        ])->render();

        $this->assertStringContainsString('Zufahrt war frei, Hof geraeumt', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
    }

    /**
     * Die Mail nennt die Fahrernotiz nicht; als Beweis, daß der Inhalt
     * wirklich gerendert wurde, dient der Name des Fahrers im Text.
     */
    public function test_the_rendered_completion_mail_does_not_contain_the_internal_note(): void
    {
        $job = $this->finishedJobWithBothNotes();

        $html = (new JobCompletedMail($job, false))->render();

        $this->assertStringContainsString($job->user->name, $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
    }
}
