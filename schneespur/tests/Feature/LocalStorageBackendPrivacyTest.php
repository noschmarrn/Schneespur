<?php

namespace Tests\Feature;

use App\Services\Storage\LocalStorageBackend;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalStorageBackendPrivacyTest extends TestCase
{
    private LocalStorageBackend $backend;

    protected function setUp(): void
    {
        parent::setUp();

        // Both disks, not just one: this test is about which of the two gets
        // written, so leaving either one real would leak state between runs.
        Storage::fake('local');
        Storage::fake('public');

        $this->backend = new LocalStorageBackend();
    }

    public function test_stored_bytes_land_on_the_private_disk(): void
    {
        $this->backend->store('photos/probe.jpg', 'BYTES');

        $this->assertTrue(Storage::disk('local')->exists('photos/probe.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('photos/probe.jpg'));
    }

    public function test_legacy_files_on_the_public_disk_stay_readable(): void
    {
        Storage::disk('public')->put('photos/legacy.jpg', 'OLD');

        $this->assertSame('OLD', $this->backend->retrieve('photos/legacy.jpg'));
        $this->assertTrue($this->backend->exists('photos/legacy.jpg'));
    }

    public function test_the_private_copy_wins_over_a_legacy_copy(): void
    {
        Storage::disk('public')->put('photos/probe.jpg', 'OLD');
        $this->backend->store('photos/probe.jpg', 'NEW');

        $this->assertSame('NEW', $this->backend->retrieve('photos/probe.jpg'));
    }

    public function test_delete_removes_both_copies(): void
    {
        Storage::disk('public')->put('photos/probe.jpg', 'OLD');
        $this->backend->store('photos/probe.jpg', 'NEW');

        $this->backend->delete('photos/probe.jpg');

        $this->assertFalse($this->backend->exists('photos/probe.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('photos/probe.jpg'));
    }

    public function test_missing_file_returns_null(): void
    {
        $this->assertNull($this->backend->retrieve('photos/nope.jpg'));
        $this->assertFalse($this->backend->exists('photos/nope.jpg'));
    }
}
