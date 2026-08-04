<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the installer out of reach once the application is installed.
 *
 * Runs *after* StartSession, because the wizard's trailing steps (mail, cron,
 * done) are only reachable for the one session that created the admin account —
 * and creating that account is exactly what marks the app as installed. The
 * environment preparation that has to happen before sessions start lives in
 * InstallerBootstrap.
 */
class InstallerGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $isPostInstallRoute = $request->routeIs('install.mail')
            || $request->routeIs('install.mail.*')
            || $request->routeIs('install.cron')
            || $request->routeIs('install.cron.*')
            || $request->routeIs('install.done');

        if ($this->isInstalled()) {
            if ($isPostInstallRoute && $request->session()->get('installer_post_install_access') === true) {
                return $next($request);
            }

            abort(410, __('install.already_installed', ['app_name' => brand()]));
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        if (file_exists(storage_path('app/installed.lock'))) {
            return true;
        }

        try {
            return DB::table('users')->count() > 0;
        } catch (\PDOException) {
            // DB not configured yet — installer should proceed
        } catch (\Illuminate\Database\QueryException) {
            // DB configured but tables don't exist yet — installer should proceed
        }

        return false;
    }
}
