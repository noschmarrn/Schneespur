<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BrandingTest extends TestCase
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

    private function admin(): User
    {
        $u = User::create(['name' => 'A', 'email' => 'a@test.local', 'password' => Hash::make('password')]);
        $u->role = UserRole::Admin;
        $u->save();

        return $u->fresh();
    }

    private function runBrandSeedMigration(): void
    {
        Setting::where('key', 'app_brand')->delete();
        (require database_path('migrations/2026_05_10_000001_seed_app_brand_setting.php'))->up();
    }

    /**
     * The Docker/Coolify deployment never runs the web installer — only
     * `migrate`. The seed migration is therefore the single source of the
     * brand there and must honour the configured install locale.
     */
    public function test_seed_migration_derives_brand_from_install_locale(): void
    {
        config(['app.locale' => 'en']);
        $this->runBrandSeedMigration();

        $this->assertSame('wintertrace', Setting::get('app_brand'));
        $this->assertSame('Wintertrace', brand());
    }

    public function test_seed_migration_keeps_schneespur_for_german_installs(): void
    {
        config(['app.locale' => 'de']);
        $this->runBrandSeedMigration();

        $this->assertSame('schneespur', Setting::get('app_brand'));
        $this->assertSame('Schneespur', brand());
    }

    public function test_seed_migration_never_overwrites_an_existing_brand(): void
    {
        Setting::set('app_brand', 'wintertrace');
        config(['app.locale' => 'de']);

        (require database_path('migrations/2026_05_10_000001_seed_app_brand_setting.php'))->up();

        $this->assertSame('wintertrace', Setting::get('app_brand'));
    }

    public function test_admin_can_switch_the_brand(): void
    {
        Setting::set('app_brand', 'schneespur');

        $response = $this->actingAs($this->admin())->post(route('admin.settings.company.update'), [
            'company_name' => 'ACME',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'en',
            'app_brand' => 'wintertrace',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('wintertrace', Setting::get('app_brand'));
        $this->assertSame('Wintertrace', brand());
    }

    public function test_unknown_brand_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.settings.company.update'), [
            'company_name' => 'ACME',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'de',
            'app_brand' => 'schneeflocke',
        ]);

        $response->assertSessionHasErrors('app_brand');
    }

    public function test_omitting_the_brand_leaves_it_untouched(): void
    {
        Setting::set('app_brand', 'wintertrace');

        $response = $this->actingAs($this->admin())->post(route('admin.settings.company.update'), [
            'company_name' => 'ACME',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'de',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('wintertrace', Setting::get('app_brand'));
    }

    /**
     * A Wintertrace install must not send mail as "Schneespur". The stock
     * .env ships MAIL_FROM_NAME="${APP_NAME}", so an unset from-name has to
     * fall back to the resolved brand rather than to APP_NAME.
     */
    public function test_unset_mail_from_name_falls_back_to_the_brand(): void
    {
        Setting::set('app_brand', 'wintertrace');

        $this->assertSame('Wintertrace', brand_mail_from_name(null));
        $this->assertSame('Wintertrace', brand_mail_from_name(''));
    }

    public function test_an_operator_chosen_mail_from_name_is_kept(): void
    {
        Setting::set('app_brand', 'wintertrace');

        $this->assertSame('ACME Winterdienst', brand_mail_from_name('ACME Winterdienst'));
    }

    public function test_provider_applies_the_mail_from_name_fallback(): void
    {
        Setting::set('app_brand', 'wintertrace');
        config(['mail.from.name' => null]);

        $this->app->register(AppServiceProvider::class, true);

        $this->assertSame('Wintertrace', config('mail.from.name'));
    }
}
