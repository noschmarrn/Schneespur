<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\User;
use App\Models\WorkShift;
use App\Policies\JobPhotoPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class JobPhotoAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    /**
     * The project has NO model factories (`database/factories/` does not exist).
     * The suite creates rows directly — see NotificationChannelRegistryTest for
     * the canonical shift/customer/object/job chain.
     */
    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' ' . (++$this->seq),
            'email' => $role . $this->seq . '@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        // assignRole() syncs the pivot; hasRole() reads the loaded relation.
        return $user->fresh();
    }

    private function makeCustomer(bool $showPhotos = true): Customer
    {
        return Customer::create([
            'name' => 'Customer ' . (++$this->seq),
            'portal_show_photos' => $showPhotos,
        ]);
    }

    private function photoFor(User $driver, Customer $customer): JobPhoto
    {
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Object ' . (++$this->seq),
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        $job = Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        return JobPhoto::create([
            'job_id' => $job->id,
            'file_path' => 'photos/' . $job->id . '-full.jpg',
            'thumbnail_path' => 'photos/' . $job->id . '-thumb.jpg',
        ]);
    }

    public function test_admin_sees_every_photo(): void
    {
        $admin = $this->makeUser('admin');
        $driver = $this->makeUser('driver');

        $photo = $this->photoFor($driver, $this->makeCustomer());

        $this->assertTrue(app(JobPhotoPolicy::class)->view($admin, $photo));
    }

    public function test_driver_sees_only_their_own_jobs(): void
    {
        $mine = $this->makeUser('driver');
        $theirs = $this->makeUser('driver');
        $customer = $this->makeCustomer();

        $this->assertTrue(app(JobPhotoPolicy::class)->view($mine, $this->photoFor($mine, $customer)));
        $this->assertFalse(app(JobPhotoPolicy::class)->view($mine, $this->photoFor($theirs, $customer)));
    }

    public function test_customer_sees_own_jobs_only_when_the_toggle_is_on(): void
    {
        $driver = $this->makeUser('driver');

        $shown = $this->makeCustomer(showPhotos: true);
        $hidden = $this->makeCustomer(showPhotos: false);

        $this->assertTrue(app(JobPhotoPolicy::class)->view($shown, $this->photoFor($driver, $shown)));
        $this->assertFalse(app(JobPhotoPolicy::class)->view($hidden, $this->photoFor($driver, $hidden)));

        // The trap: right toggle, wrong customer. Checking only the toggle, or
        // only the ownership, passes the two cases above and fails this one.
        $this->assertFalse(app(JobPhotoPolicy::class)->view($shown, $this->photoFor($driver, $hidden)));
    }

    public function test_deleting_the_job_takes_its_photos_with_it(): void
    {
        $driver = $this->makeUser('driver');
        $photo = $this->photoFor($driver, $this->makeCustomer());

        $photo->job->delete();

        // job_photos.job_id is cascadeOnDelete, so deleting the job is what
        // makes a photo unreachable — there is no row left to authorise.
        $this->assertNull($photo->fresh());
    }

    public function test_a_customer_never_falls_through_to_the_driver_branch(): void
    {
        $driver = $this->makeUser('driver');
        $customer = $this->makeCustomer();

        // The ids of the two guards live in separate spaces and do collide in
        // practice. A customer must not be compared against job.user_id.
        $photo = $this->photoFor($driver, $this->makeCustomer());

        $this->assertSame($driver->id, $photo->job->user_id);
        $this->assertFalse(app(JobPhotoPolicy::class)->view($customer, $photo));
    }

    /**
     * Everything above tests the policy in isolation. This one proves the wiring:
     * that AppServiceProvider actually claims 'photos/', that the signed route
     * reaches the policy, and that a denial really withholds the bytes.
     */
    public function test_a_driver_cannot_fetch_another_drivers_photo_over_the_media_route(): void
    {
        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
        Storage::fake('local');
        Storage::fake('public');

        $mine = $this->makeUser('driver');
        $theirs = $this->makeUser('driver');
        $customer = $this->makeCustomer();

        $ownPhoto = $this->photoFor($mine, $customer);
        $otherPhoto = $this->photoFor($theirs, $customer);

        Storage::disk('local')->put($ownPhoto->file_path, 'MINE');
        Storage::disk('local')->put($otherPhoto->file_path, 'THEIRS');

        $this->actingAs($mine);

        $this->get(URL::temporarySignedRoute('media.show', now()->addDay(), ['path' => $ownPhoto->file_path]))
            ->assertStatus(200);

        $response = $this->get(
            URL::temporarySignedRoute('media.show', now()->addDay(), ['path' => $otherPhoto->file_path])
        );

        // A valid signature is not authorisation — the gate still has to refuse.
        $response->assertStatus(403);
        $this->assertStringNotContainsString('THEIRS', $response->getContent());

        @unlink($lockFile);
    }

    public function test_guest_sees_nothing(): void
    {
        $driver = $this->makeUser('driver');

        $this->assertFalse(
            app(JobPhotoPolicy::class)->view(null, $this->photoFor($driver, $this->makeCustomer()))
        );
    }
}
