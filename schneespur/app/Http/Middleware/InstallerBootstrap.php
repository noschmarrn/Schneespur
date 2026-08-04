<?php

namespace App\Http\Middleware;

use App\Services\Installer\EnvFileWriter;
use Closure;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepares the environment the installer needs before cookies and sessions start.
 *
 * This has to run first in the `installer` group: it writes .env and APP_KEY
 * (which EncryptCookies needs) and forces the session and cache onto the file
 * driver (which StartSession needs, because there is no database yet).
 *
 * The installed-state check lives in InstallerGuard, which runs *after*
 * StartSession because it reads the session (M018/S01).
 */
class InstallerBootstrap
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
