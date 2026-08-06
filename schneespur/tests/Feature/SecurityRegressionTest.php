<?php

namespace Tests\Feature;

use App\Models\DsgvoConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Findings from the 2026-08-03 pentests that no other test file owns (M018/S06).
 *
 * Everything else has a home: InstallerGuardTest, MarkdownSafeTest,
 * DsgvoMarkdownXssTest, ModuleKillSwitchTest, ManualJobDriverValidationTest,
 * WeatherUserAgentHeaderTest, ModuleApiAuthTest, plus the media tests from S04
 * and composer audit in CI for the dompdf CVEs.
 */
class SecurityRegressionTest extends TestCase
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

    /**
     * The fourth render path from vuln-0003: the frozen snapshot.
     *
     * S02 swapped the three live DSGVO render paths to markdown_safe(). This one
     * renders text captured at confirmation time, so it can still hold a payload
     * written before the fix — the row is evidence and is never rewritten.
     */
    public function test_dsgvo_confirmation_snapshot_strips_javascript_scheme(): void
    {
        // The project has NO model factories — the suite creates rows directly.
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        $confirmation = DsgvoConfirmation::create([
            'driver_id' => $driver->id,
            'signed_by' => 'Driver',
            'template_version' => 1,
            'notice_text_snapshot' => '[stored](javascript:document.body.dataset.stored=1)',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin->fresh())
            ->get("/admin/dsgvo/confirmations/{$confirmation->id}");

        $response->assertStatus(200);

        // Guard against a green for the wrong reason: if the view stopped
        // rendering the snapshot at all, the payload assertion below would pass
        // while proving nothing.
        $response->assertSee('stored');
        $this->assertStringNotContainsString('javascript:document.body', $response->getContent());
    }

    /**
     * The same snapshot path with an inline <script>, which takes a different
     * route through the renderer than a javascript: URL does.
     */
    public function test_dsgvo_confirmation_snapshot_strips_inline_script(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver2@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        $confirmation = DsgvoConfirmation::create([
            'driver_id' => $driver->id,
            'signed_by' => 'Driver',
            'template_version' => 1,
            'notice_text_snapshot' => "Hinweis\n\n<script>document.body.dataset.x=1</script>",
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin->fresh())
            ->get("/admin/dsgvo/confirmations/{$confirmation->id}");

        $response->assertStatus(200);
        $response->assertSee('Hinweis');
        $this->assertStringNotContainsString('<script>document.body', $response->getContent());
    }
}
