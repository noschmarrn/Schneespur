<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerGuardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private bool $envExistedBefore = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Hitting an installer route runs InstallerBootstrap, which writes a real
        // .env with a fresh APP_KEY. Remember whether one was already there so
        // tearDown can leave the working tree exactly as it found it — and never
        // delete an .env that belongs to a real installation.
        $this->envExistedBefore = file_exists(base_path('.env'));

        // InstallerBootstrap forces the session onto the file driver mid-request.
        // Seed the session through the same driver, otherwise withSession() writes
        // to whatever SESSION_DRIVER happens to be configured (Laravel's default is
        // 'database') and StartSession then reads an empty file session. That made
        // this test depend on the developer's .env — a hidden dependency that would
        // surface in CI as a flake.
        config(['session.driver' => 'file']);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));

        if (! $this->envExistedBefore) {
            @unlink(base_path('.env'));
        }

        parent::tearDown();
    }

    private function markInstalledViaLockFile(): void
    {
        $path = storage_path('app/installed.lock');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'test');
    }

    /** Lockfile vorhanden: die Nachlauf-Schritte sind für Fremde zu. */
    public function test_post_install_routes_are_gone_for_strangers_when_locked(): void
    {
        $this->markInstalledViaLockFile();

        $this->get('/install/mail')->assertStatus(410);
        $this->get('/install/cron')->assertStatus(410);
        $this->get('/install/done')->assertStatus(410);
    }

    /** Der inkonsistente Fall: Lockfile weg, users befüllt — trotzdem zu. */
    public function test_post_install_routes_are_gone_when_users_exist_without_lockfile(): void
    {
        @unlink(storage_path('app/installed.lock'));

        // The project has no model factories — the suite creates rows directly.
        User::create([
            'name' => 'Existing Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);

        $this->get('/install/mail')->assertStatus(410);
        $this->get('/install/cron')->assertStatus(410);
    }

    /** Der eigentliche Angriff: schreibender Zugriff ohne Login. */
    public function test_cron_test_cannot_be_triggered_by_strangers(): void
    {
        $this->markInstalledViaLockFile();

        $this->post('/install/cron/test')->assertStatus(410);
    }

    /** Die Session, die den Admin angelegt hat, darf weiterlaufen. */
    public function test_installer_session_may_continue_to_mail_and_cron(): void
    {
        $this->markInstalledViaLockFile();

        $this->withSession(['installer_post_install_access' => true])
            ->get('/install/mail')
            ->assertStatus(200);

        $this->withSession(['installer_post_install_access' => true])
            ->get('/install/cron')
            ->assertStatus(200);
    }

    /** Nach der Abschlussseite ist der Wizard endgültig zu. */
    public function test_done_page_clears_the_continuation_flag(): void
    {
        $this->markInstalledViaLockFile();

        $this->withSession(['installer_post_install_access' => true])
            ->get('/install/done')
            ->assertStatus(200)
            ->assertSessionMissing('installer_post_install_access');
    }

    /** Nicht installiert: der Wizard läuft normal an. */
    public function test_welcome_is_reachable_when_not_installed(): void
    {
        @unlink(storage_path('app/installed.lock'));

        $this->get('/install')->assertStatus(200);
    }
}
