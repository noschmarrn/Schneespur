<?php

namespace App\Http\Middleware;

use App\Services\Extension\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isDriver()) {
            return redirect()->route('dashboard');
        }

        if ($user->locale && app(LocaleRegistry::class)->has($user->locale)) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}
