<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\JobAudit;
use App\Models\User;
use App\Services\JobLifecycleService;
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
}
