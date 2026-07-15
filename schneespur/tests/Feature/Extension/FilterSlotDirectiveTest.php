<?php

namespace Tests\Feature\Extension;

use App\Services\Extension\FilterRegistry;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FilterSlotDirectiveTest extends TestCase
{
    public function test_filter_slot_renders_registered_callback_html(): void
    {
        app(FilterRegistry::class)->register(
            'test.job.detail',
            fn (array $html, $job): array => [...$html, "<p>consumed:{$job->id}</p>"]
        );

        $out = Blade::render("@filterSlot('test.job.detail', [], \$job)", ['job' => (object) ['id' => 42]]);

        $this->assertStringContainsString('<p>consumed:42</p>', $out);
    }

    public function test_filter_slot_swallows_throwing_callback(): void
    {
        app(FilterRegistry::class)->register(
            'test.job.detail',
            function (array $html, $job): array { throw new \RuntimeException('boom'); }
        );

        $out = Blade::render("@filterSlot('test.job.detail', [], \$job)", ['job' => (object) ['id' => 7]]);

        $this->assertSame('', $out);
    }

    public function test_admin_job_show_view_wires_the_seam(): void
    {
        $this->assertStringContainsString(
            "@filterSlot('schneespur.admin.job.detail.after', [], \$job)",
            file_get_contents(resource_path('views/admin/jobs/show.blade.php'))
        );
    }
}
