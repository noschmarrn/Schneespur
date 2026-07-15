<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class ManualJobObjectValidationTest extends TestCase
{
    public function test_object_select_is_conditionally_required_not_statically(): void
    {
        $blade = file_get_contents(resource_path('views/admin/jobs/manual/create.blade.php'));

        // The bound (conditional) required must be present …
        $this->assertStringContainsString('x-bind:required="objects.length > 1"', $blade);
        // … and the object <select> must no longer carry a bare `required` attribute.
        $this->assertStringNotContainsString('name="customer_object_id" x-model.number="selectedObjectId" required', $blade);
    }

    public function test_no_objects_hint_key_exists(): void
    {
        $this->assertArrayHasKey('manual_no_objects', require lang_path('en/job.php'));
        $this->assertArrayHasKey('manual_no_objects', require lang_path('de/job.php'));
    }

    public function test_no_objects_hint_is_wired_in_view(): void
    {
        $blade = file_get_contents(resource_path('views/admin/jobs/manual/create.blade.php'));

        $this->assertStringContainsString('objects.length === 0', $blade);
        $this->assertStringContainsString("__('job.manual_no_objects')", $blade);
    }
}
