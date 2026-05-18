<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetInstallerLocale
{
    // Order matters: Symfony's getPreferredLanguage falls back to the FIRST list
    // entry when no browser language matches, so 'en' must lead — that makes
    // zh-CN, ja, ar, etc. resolve to en/Wintertrace instead of de/Schneespur.
    private const SUPPORTED = ['en', 'de'];

    private const FALLBACK = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session()->get('installer_locale');

        if (in_array($session, self::SUPPORTED, true)) {
            $locale = $session;
        } else {
            $locale = $request->getPreferredLanguage(self::SUPPORTED) ?: self::FALLBACK;
            $request->session()->put('installer_locale', $locale);
        }

        App::setLocale($locale);
        View::share('installerLocale', $locale);

        return $next($request);
    }
}
