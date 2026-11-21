<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SchneespurUpdater;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Der Updater arbeitet in zwei Requests (ab 1.2.2):
 *
 *   Stufe 1 (stage)    — alter Code: Dateien kopieren, Caches und OPcache
 *                        leeren, Wartungsmodus mit gespeichertem Secret.
 *   Stufe 2 (finalize) — frischer Prozeß, also neuer Code: migrieren,
 *                        Caches, State festschreiben, Wartungsmodus aus.
 *
 * In 1.2.1 lief beides in einem Prozeß; die neuen Migrationen sahen die
 * alten, schon geladenen Klassen, und jede per Knopf aktualisierte
 * Installation blieb bei 503 stehen.
 *
 * Alle Pfade zeigen in ein Wegwerfverzeichnis: stage() schreibt sonst über
 * das Repository.
 */
class UpdateTwoStageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/schneespur-two-stage-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir.'/target/bootstrap/cache');
        file_put_contents($this->dir.'/target/VERSION', "1.2.1\n");
        file_put_contents($this->dir.'/target/bootstrap/cache/config.php', '<?php return [];');
        file_put_contents($this->dir.'/target/bootstrap/cache/packages.php', '<?php return [];');
        file_put_contents($this->dir.'/state.json', json_encode(['current_version' => '1.2.1', 'last_counter' => 4]));

        config([
            'schneespur_update.install_dir'   => $this->dir.'/target',
            'schneespur_update.staging_dir'   => $this->dir.'/staging',
            'schneespur_update.backup_dir'    => $this->dir.'/backups',
            'schneespur_update.state_path'    => $this->dir.'/state.json',
            'schneespur_update.pending_path'  => $this->dir.'/pending.json',
            'schneespur_update.recovery_path' => $this->dir.'/recovery.json',
            // Echt gebaut landeten die Caches in bootstrap/cache des Repos, und
            // jeder spätere Test liefe gegen eingefrorene Konfiguration und Routen.
            'schneespur_update.build_caches'  => false,
        ]);

        $lock = storage_path('app/installed.lock');
        if (! file_exists($lock)) {
            @mkdir(dirname($lock), 0755, true);
            file_put_contents($lock, 'test');
        }
    }

    protected function tearDown(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
        File::deleteDirectory($this->dir);
        @unlink(storage_path('app/installed.lock'));

        parent::tearDown();
    }

    private function zip(array $files): string
    {
        $path = $this->dir.'/update.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function manifest(): array
    {
        return ['version' => '1.2.2', 'counter' => 5];
    }

    private function state(): array
    {
        return json_decode(file_get_contents($this->dir.'/state.json'), true);
    }

    public function test_stage_copies_the_files_and_leaves_the_finishing_to_a_second_request(): void
    {
        $secret = (new SchneespurUpdater)->stage(
            $this->zip(['VERSION' => "1.2.2\n", 'app/New.php' => '<?php // neu']),
            $this->manifest(),
        );

        $this->assertSame("1.2.2\n", file_get_contents($this->dir.'/target/VERSION'));
        $this->assertFileExists($this->dir.'/target/app/New.php');

        // Die nächste Anfrage muß die neue Konfiguration, die neuen Routen und
        // die neuen Provider laden, nicht die gecachten alten.
        $this->assertSame([], glob($this->dir.'/target/bootstrap/cache/*.php'));

        $this->assertTrue(app()->isDownForMaintenance());
        $pending = json_decode(file_get_contents($this->dir.'/pending.json'), true);
        $this->assertSame('1.2.2', $pending['version']);
        $this->assertSame($secret, $pending['secret']);

        // Noch nicht festgeschrieben: das tut erst Stufe 2.
        $this->assertSame('1.2.1', $this->state()['current_version']);
    }

    public function test_finalize_commits_the_version_and_lifts_maintenance_mode(): void
    {
        $updater = new SchneespurUpdater;
        $updater->stage($this->zip(['VERSION' => "1.2.2\n"]), $this->manifest());

        $result = (new SchneespurUpdater)->finalize();

        $this->assertSame('1.2.2', $result['version']);
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertFileDoesNotExist($this->dir.'/pending.json');
        $this->assertSame('1.2.2', $this->state()['current_version']);
        $this->assertSame(5, $this->state()['last_counter']);
    }

    public function test_finalize_without_a_pending_update_does_nothing(): void
    {
        $this->assertNull((new SchneespurUpdater)->finalize());
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_a_failure_before_any_file_is_copied_does_not_leave_the_site_down(): void
    {
        file_put_contents($this->dir.'/broken.zip', 'kein zip');

        try {
            (new SchneespurUpdater)->stage($this->dir.'/broken.zip', $this->manifest());
            $this->fail('stage() hätte werfen müssen');
        } catch (RuntimeException) {
        }

        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertFileDoesNotExist($this->dir.'/pending.json');
        $this->assertSame("1.2.1\n", file_get_contents($this->dir.'/target/VERSION'));
    }

    public function test_a_failed_finalize_keeps_maintenance_and_can_be_retried(): void
    {
        (new SchneespurUpdater)->stage($this->zip(['VERSION' => "1.2.2\n"]), $this->manifest());

        $real = Artisan::getFacadeRoot();
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $args = []) use ($real) {
            if ($command === 'migrate') {
                throw new RuntimeException('Tabelle gesperrt');
            }

            return $real->call($command, $args);
        });

        try {
            (new SchneespurUpdater)->finalize();
            $this->fail('finalize() hätte werfen müssen');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Tabelle gesperrt', $e->getMessage());
        }

        $this->assertTrue(app()->isDownForMaintenance());
        $this->assertFileExists($this->dir.'/pending.json');
        $recovery = json_decode(file_get_contents($this->dir.'/recovery.json'), true);
        $this->assertStringStartsWith('/', $recovery['bypass_path']);
        $this->assertSame('1.2.1', $this->state()['current_version']);

        Artisan::swap($real);

        $this->assertSame('1.2.2', (new SchneespurUpdater)->finalize()['version']);
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertFileDoesNotExist($this->dir.'/recovery.json');
    }

    public function test_update_recover_finishes_a_pending_update(): void
    {
        (new SchneespurUpdater)->stage($this->zip(['VERSION' => "1.2.2\n"]), $this->manifest());

        $this->artisan('schneespur:update-recover', ['--force' => true])->assertExitCode(0);

        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertSame('1.2.2', $this->state()['current_version']);
    }

    // ── HTTP ─────────────────────────────────────────────

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    /**
     * Versionsübergreifender Vertrag: Stufe 1 der ALTEN Version schickt den
     * Browser an genau diesen Pfad, und der Code der NEUEN Version muß ihn
     * beantworten — mitten im Wartungsmodus, über das Bypass-Cookie.
     */
    public function test_the_finalize_route_answers_during_maintenance_with_the_bypass_cookie(): void
    {
        $admin = $this->admin();
        $secret = (new SchneespurUpdater)->stage($this->zip(['VERSION' => "1.2.2\n"]), $this->manifest());

        $this->actingAs($admin)
            ->postJson('/admin/settings/update/finalize')
            ->assertStatus(503);

        $this->actingAs($admin)
            ->withCredentials() // postJson() schickt Cookies sonst gar nicht mit; fetch() im Browser tut es
            ->withUnencryptedCookie('laravel_maintenance', MaintenanceModeBypassCookie::create($secret)->getValue())
            ->postJson('/admin/settings/update/finalize')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertSame('1.2.2', $this->state()['current_version']);
    }

    /**
     * Das Cookie kommt aus einer Controller-Antwort und liefe sonst durch
     * EncryptCookies — PreventRequestsDuringMaintenance läuft davor und
     * erwartet den Rohwert. Verschlüsselt hätte der Browser Stufe 2 nie
     * erreicht; der Test oben (mit Rohwert) wäre trotzdem grün gewesen.
     */
    public function test_the_bypass_cookie_is_never_encrypted(): void
    {
        $this->assertTrue(app(\Illuminate\Cookie\Middleware\EncryptCookies::class)->isDisabled('laravel_maintenance'));
    }

    public function test_the_update_page_offers_to_finish_a_pending_update(): void
    {
        file_put_contents($this->dir.'/pending.json', json_encode([
            'version' => '1.2.2', 'counter' => 5, 'secret' => 'x', 'backup_dir' => '', 'started_at' => now()->toIso8601String(),
        ]));

        $this->actingAs($this->admin())
            ->get('/admin/settings/update')
            ->assertOk()
            ->assertSee(__('update.pending_notice', ['version' => '1.2.2']))
            ->assertSee(__('update.finalize_button'));
    }
}
