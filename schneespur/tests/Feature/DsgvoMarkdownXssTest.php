<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DsgvoMarkdownXssTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PAYLOAD = '[stored](javascript:document.body.dataset.stored=1)';

    private bool $lockExistedBefore = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Without the lock file RedirectToInstaller sends every web request to
        // the installer, which would turn these assertions into false greens.
        $lockFile = storage_path('app/installed.lock');
        $this->lockExistedBefore = file_exists($lockFile);

        if (! $this->lockExistedBefore) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        if (! $this->lockExistedBefore) {
            @unlink(storage_path('app/installed.lock'));
        }

        parent::tearDown();
    }

    /** The project has NO model factories — the suite creates rows directly. */
    private function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->makeUser('admin', 'admin@test.local');
    }

    public function test_preview_endpoint_strips_javascript_scheme(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/admin/dsgvo/preview', ['markdown' => self::PAYLOAD]);

        $response->assertStatus(200);
        $this->assertStringNotContainsString('href="javascript:', $response->getContent());
        // The link must still be rendered — otherwise this passes for the wrong
        // reason (nothing rendered at all).
        $this->assertStringContainsString('<a>stored</a>', $response->getContent());
    }

    public function test_admin_index_preview_strips_javascript_scheme(): void
    {
        Setting::set('dsgvo_template_markdown', self::PAYLOAD);

        $response = $this->actingAs($this->admin())->get('/admin/dsgvo');

        $response->assertStatus(200);

        // Assert on the view value, not the page text: the admin page embeds the
        // rendered HTML through @json(), which escapes quotes — a substring check
        // for href="javascript: would silently pass even while vulnerable. The
        // raw markdown also legitimately appears in the editor textarea, so the
        // page body is the wrong place to look.
        $response->assertViewHas(
            'previewHtml',
            fn (string $html): bool => ! str_contains($html, 'href="javascript:')
                && str_contains($html, '<a>stored</a>')
        );
    }

    public function test_onboarding_page_strips_javascript_scheme(): void
    {
        Setting::set('dsgvo_template_markdown', self::PAYLOAD);

        $driver = $this->makeUser('driver', 'driver@test.local');

        $response = $this->actingAs($driver)->get('/onboarding/dsgvo');

        // Assert the status too: a redirect would trivially "not contain" the
        // payload and pass without ever rendering the page.
        $response->assertStatus(200);
        $this->assertStringNotContainsString('href="javascript:', $response->getContent());
        $this->assertStringContainsString('<a>stored</a>', $response->getContent());
    }
}
