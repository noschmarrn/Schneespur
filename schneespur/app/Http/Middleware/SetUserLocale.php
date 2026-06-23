<?php

namespace App\Http\Middleware;

use App\Services\Extension\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the authenticated web user's per-user UI locale, overriding the
 * app-wide default_locale set at boot. Runs for every authenticated web user
 * (admin and driver alike), so the per-user picker in admin/users takes effect
 * across the whole authenticated UI, not just driver routes.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->locale && app(LocaleRegistry::class)->has($user->locale)) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}
