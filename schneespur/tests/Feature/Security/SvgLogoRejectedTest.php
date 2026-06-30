<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SvgLogoRejectedTest extends TestCase
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
     * Guards the raster-only contract: an SVG logo (which can carry script /
     * onload XSS) must be rejected. If this ever fails because `allow_svg` was
     * added, the upload path must sanitize SVGs before storing them.
     */
    public function test_svg_logo_upload_is_rejected(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->role = UserRole::Admin;
        $admin->save();

        $svg = '<?xml version="1.0"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            .'<script>alert(document.cookie)</script>'
            .'</svg>';

        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $response = $this->actingAs($admin->fresh())
            ->post('/admin/settings/branding', ['company_logo' => $file]);

        $response->assertSessionHasErrors('company_logo');
        $this->assertEmpty(Setting::get('company_logo_path'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }
}
