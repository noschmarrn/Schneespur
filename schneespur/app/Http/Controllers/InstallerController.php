<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Installer\EnvFileWriter;
use App\Services\Installer\InstallLockManager;
use App\Services\Installer\MigrationRunner;
use App\Services\Installer\PreflightChecker;
use App\Services\Installer\StorageConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use PDO;

class InstallerController extends Controller
{
    public function __construct(
        private EnvFileWriter $envWriter,
        private PreflightChecker $preflightChecker,
        private MigrationRunner $migrationRunner,
        private StorageConfigurator $storageConfigurator,
        private InstallLockManager $lockManager,
    ) {}

    // --- Locale switcher (works on any installer step) ---

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, ['de', 'en'], true)) {
            $request->session()->put('installer_locale', $locale);
        }

        return redirect($request->headers->get('referer') ?: route('install.welcome'));
    }

    // --- Step 1: Welcome ---

    public function showWelcome(Request $request): View
    {
        $this->autoDetectAppUrl($request);

        return view('installer.step1-welcome', ['currentStep' => 1]);
    }

    public function processWelcome(): RedirectResponse
    {
        return redirect()->route('install.preflight');
    }

    // --- Step 2: Preflight (was Step 3) ---

    // --- Step 3: Database (was Step 2) ---

    public function showDatabase(): View
    {
        return view('installer.step2-database', [
            'currentStep' => 3,
            'env_content' => null,
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        $password = $validated['db_password'] ?? '';

        try {
            new PDO(
                "mysql:host={$validated['db_host']};port={$validated['db_port']};dbname={$validated['db_database']}",
                $validated['db_username'],
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (\PDOException $e) {
            return redirect()->route('install.database')
                ->withInput()
                ->withErrors(['db_connection' => __('install.error_db_connection') . ' (' . $e->getMessage() . ')']);
        }

        $envValues = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $validated['db_host'],
            'DB_PORT' => (string) $validated['db_port'],
            'DB_DATABASE' => $validated['db_database'],
            'DB_USERNAME' => $validated['db_username'],
            'DB_PASSWORD' => $password,
        ];

        if (! $this->envWriter->isWritable()) {
            $this->envWriter->setMany($envValues);
            Artisan::call('config:clear');

            if (! $this->envWriter->isWritable()) {
                return redirect()->route('install.database')
                    ->withInput()
                    ->with('env_content', $this->envWriter->getFullContent())
                    ->withErrors(['env_write' => __('install.error_env_write')]);
            }
        }

        $this->envWriter->setMany($envValues);
        Artisan::call('config:clear');

        return redirect()->route('install.migrations');
    }

    public function showPreflight(): View
    {
        return view('installer.step3-preflight', [
            'currentStep' => 2,
            'checks' => $this->preflightChecker->check(),
            'hasCritical' => $this->preflightChecker->hasCriticalFailures(),
        ]);
    }

    public function processPreflight(): RedirectResponse
    {
        if ($this->preflightChecker->hasCriticalFailures()) {
            return redirect()->route('install.preflight')
                ->withErrors(['preflight' => __('install.preflight_has_failures')]);
        }

        return redirect()->route('install.database');
    }

    // --- Step 4: Migrations ---

    public function showMigrations(): View
    {
        return view('installer.step4-migrations', ['currentStep' => 4]);
    }

    public function runMigrations(): RedirectResponse
    {
        $result = $this->migrationRunner->run();

        if (! $result['success']) {
            return redirect()->route('install.migrations')
                ->withErrors(['migration' => __('install.error_migration_main')])
                ->with('migration_output', $result['error'] ?? $result['output']);
        }

        $this->envWriter->setMany([
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
        ]);
        Artisan::call('config:clear');

        return redirect()->route('install.config');
    }

    // --- Step 5: Config ---

    public function showConfig(Request $request): View
    {
        $detectedUrl = $request->schemeAndHttpHost();

        return view('installer.step5-config', [
            'currentStep' => 5,
            'app_url' => $this->envWriter->get('APP_URL') ?: $detectedUrl,
            'timezone' => $this->envWriter->get('APP_DISPLAY_TIMEZONE') ?: 'Europe/Berlin',
        ]);
    }

    public function storeConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_url' => 'required|url',
            'timezone' => 'required|string|timezone:all',
            'locale' => 'required|string|in:de,en',
        ]);

        $this->envWriter->setMany([
            'APP_URL' => $validated['app_url'],
            'APP_DISPLAY_TIMEZONE' => $validated['timezone'],
            'APP_LOCALE' => $validated['locale'],
        ]);

        Artisan::call('config:clear');

        $brand = $validated['locale'] === 'de' ? 'schneespur' : 'wintertrace';

        try {
            Setting::set('app_url', $validated['app_url']);
            Setting::set('display_timezone', $validated['timezone']);
            Setting::set('locale', $validated['locale']);
            Setting::set('app_brand', $brand);
        } catch (\Exception) {
            // Settings table may not exist yet in edge cases — .env is the primary store
        }

        return redirect()->route('install.storage');
    }

    // --- Step 6: Storage ---

    public function showStorage(): View
    {
        return view('installer.step6-storage', [
            'currentStep' => 6,
            'results' => null,
        ]);
    }

    public function runStorage(): View
    {
        $results = $this->storageConfigurator->runAll();

        return view('installer.step6-storage', [
            'currentStep' => 6,
            'results' => $results,
        ]);
    }

    // --- Step 7: Admin ---

    public function showAdmin(): View
    {
        return view('installer.step7-admin', ['currentStep' => 7]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ])->forceFill(['role' => UserRole::Admin])->save();

        $this->lockManager->lock();

        try {
            Setting::set('installed_at', now()->toIso8601String());
        } catch (\Exception) {
            // Fallback: lock file is the authoritative indicator
        }

        Artisan::call('config:clear');

        return redirect()->route('install.mail');
    }

    // --- Step 8: Mail ---

    public function showMail(): View
    {
        return view('installer.step8-mail', ['currentStep' => 8]);
    }

    public function sendTestMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_host' => 'required|string',
            'mail_port' => 'required|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string|in:tls,ssl,null',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
            'test_recipient' => 'required|email',
        ]);

        $schemeMap = ['tls' => '', 'ssl' => 'smtps', 'null' => ''];
        $mailScheme = $schemeMap[$validated['mail_encryption'] ?? 'null'] ?? '';

        $this->envWriter->setMany([
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $validated['mail_host'],
            'MAIL_PORT' => (string) $validated['mail_port'],
            'MAIL_SCHEME' => $mailScheme,
            'MAIL_USERNAME' => $validated['mail_username'] ?? '',
            'MAIL_PASSWORD' => $validated['mail_password'] ?? '',
            'MAIL_FROM_ADDRESS' => $validated['mail_from_address'],
            'MAIL_FROM_NAME' => $validated['mail_from_name'],
        ]);

        Artisan::call('config:clear');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $validated['mail_host'],
            'mail.mailers.smtp.port' => $validated['mail_port'],
            'mail.mailers.smtp.username' => $validated['mail_username'],
            'mail.mailers.smtp.password' => $validated['mail_password'],
            'mail.mailers.smtp.scheme' => $mailScheme ?: null,
            'mail.from.address' => $validated['mail_from_address'],
            'mail.from.name' => $validated['mail_from_name'],
        ]);

        try {
            Mail::raw(__('install.mail_test_body', ['brand' => brand()]), function ($message) use ($validated) {
                $message->to($validated['test_recipient'])
                    ->subject(brand() . ' — ' . __('install.mail_test_subject'));
            });

            return redirect()->route('install.cron')
                ->with('flash_test_mail', __('install.flash_test_mail', ['email' => $validated['test_recipient']]));
        } catch (\Exception $e) {
            return redirect()->route('install.mail')
                ->withInput()
                ->withErrors(['mail' => $e->getMessage()]);
        }
    }

    public function skipMail(): RedirectResponse
    {
        return redirect()->route('install.cron');
    }

    // --- Step 9: Cron ---

    public function showCron(): View
    {
        $cronLine = '* * * * * ' . $this->detectPhpCli() . ' ' . base_path('artisan') . ' schedule:run >> /dev/null 2>&1';
        $cronActive = cache()->has('cron.last_run');

        return view('installer.step9-cron', [
            'currentStep' => 9,
            'cronLine' => $cronLine,
            'cronActive' => $cronActive,
        ]);
    }

    public function testCron(): RedirectResponse
    {
        try {
            Artisan::call('schedule:run');
        } catch (\Exception) {
            // Not critical
        }

        cache()->put('cron.last_run', now());

        return redirect()->route('install.cron')
            ->with('cron_test_success', true);
    }

    public function skipCron(): RedirectResponse
    {
        return redirect()->route('install.done');
    }

    // --- Helpers ---

    private function detectPhpCli(): string
    {
        $binary = PHP_BINARY;

        if (str_contains($binary, 'fpm') || str_contains($binary, 'cgi')) {
            $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            foreach (["/usr/bin/php{$version}", "/usr/bin/php", "/usr/local/bin/php"] as $candidate) {
                if (is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return $binary;
    }

    private function autoDetectAppUrl(Request $request): void
    {
        $detected = $request->getSchemeAndHttpHost();
        $current = $this->envWriter->get('APP_URL');

        if (! $current || $current === 'http://localhost') {
            $this->envWriter->set('APP_URL', $detected);
            config(['app.url' => $detected]);
            url()->forceRootUrl($detected);
            if ($request->isSecure()) {
                url()->forceScheme('https');
            }
        }
    }

    // --- Done ---

    public function showDone(): View
    {
        if (! $this->lockManager->isLocked()) {
            return view('installer.step1-welcome', ['currentStep' => 1]);
        }

        $admin = User::where('role', UserRole::Admin)->first();

        return view('installer.done', [
            'currentStep' => 10,
            'appUrl' => $this->envWriter->get('APP_URL') ?: url('/'),
            'adminEmail' => $admin?->email ?? '—',
            'mailConfigured' => ! empty($this->envWriter->get('MAIL_HOST')),
        ]);
    }
}
