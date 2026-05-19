<?php

use App\Http\Middleware\AuthenticateOwntracks;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureDriver;
use App\Http\Middleware\EnsureDsgvoInformed;
use App\Http\Middleware\InstallerGuard;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SetInstallerLocale;
use App\Services\Diagnostic\DiagnosticManager;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/install.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        TrustProxies::at('*');

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('portal', 'portal/*')) {
                return route('portal.login');
            }
            return route('login');
        });

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'driver' => EnsureDriver::class,
            'dsgvo' => EnsureDsgvoInformed::class,
            'installer.guard' => InstallerGuard::class,
            'owntracks' => AuthenticateOwntracks::class,
            'portal' => EnsureCustomer::class,
        ]);

        $middleware->prependToGroup('web', RedirectToInstaller::class);

        $middleware->group('installer', [
            TrustProxies::class,
            InstallerGuard::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            SetInstallerLocale::class,
            ValidateCsrfToken::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('jobs:retention-delete')->daily()->at('03:00');
        $schedule->command('schneespur:update-check')->daily()->at('04:17')
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schneespur-update.log'));
        $schedule->command('queue:work', ['--stop-when-empty'])->everyMinute()->withoutOverlapping();
        $schedule->call(fn () => cache()->put('cron.last_run', now()))->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e) {
            $reported = false;
            try {
                $manager = app(DiagnosticManager::class);
                if ($manager->hasEnabledReporters()) {
                    $manager->reportException($e);
                    $reported = true;
                }
            } catch (\Throwable) {
                // Never let diagnostic reporting break the application
            }

            return $reported ? false : null;
        });
    })->create();
