<?php

namespace App\Http\Middleware;

use App\Services\Installer\EnvFileWriter;
use Closure;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InstallerGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->ensureEnv();

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'session.cookie' => Str::slug((string) config('app.name', 'laravel')) . '-session',
        ]);

        $this->ensureAppUrl($request);

        if ($request->routeIs('install.done') || $request->routeIs('install.mail') || $request->routeIs('install.mail.send') || $request->routeIs('install.mail.skip') || $request->routeIs('install.cron') || $request->routeIs('install.cron.test') || $request->routeIs('install.cron.skip')) {
            return $next($request);
        }

        if (file_exists(storage_path('app/installed.lock'))) {
            abort(410, __('install.already_installed', ['app_name' => brand()]));
        }

        try {
            $userCount = DB::table('users')->count();
            if ($userCount > 0) {
                abort(410, __('install.already_installed', ['app_name' => brand()]));
            }
        } catch (\PDOException) {
            // DB not configured yet — installer should proceed
        } catch (\Illuminate\Database\QueryException) {
            // DB configured but tables don't exist yet — installer should proceed
        }

        return $next($request);
    }

    private function ensureEnv(): void
    {
        $env = app(EnvFileWriter::class);
        $env->ensureEnvExists();

        $key = $env->get('APP_KEY');

        if (empty($key)) {
            $key = 'base64:' . base64_encode(random_bytes(32));
            $env->set('APP_KEY', $key);
        }

        config(['app.key' => $key]);

        $rawKey = base64_decode(substr($key, 7));
        app()->forgetInstance('encrypter');
        app()->singleton('encrypter', fn () => new Encrypter(
            $rawKey,
            config('app.cipher'),
        ));
    }

    private function ensureAppUrl(Request $request): void
    {
        $isSecure = $request->isSecure()
            || $request->server('HTTP_X_FORWARDED_PORT') === '443'
            || $request->server('SERVER_PORT') === '443';

        if ($isSecure) {
            url()->forceScheme('https');
        }

        $currentUrl = config('app.url');

        if (! $currentUrl || $currentUrl === 'http://localhost') {
            $scheme = $isSecure ? 'https' : 'http';
            $detected = $scheme . '://' . $request->getHost();
            config(['app.url' => $detected]);
            url()->forceRootUrl($detected);

            try {
                $env = app(EnvFileWriter::class);
                $env->ensureEnvExists();
                $env->set('APP_URL', $detected);
            } catch (\Throwable) {
                // .env not writable yet — runtime override is enough for rendering
            }
        }
    }
}
