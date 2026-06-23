<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isDriver()) {
            return redirect()->route('dashboard');
        }

        // Per-user locale is applied centrally by the SetUserLocale middleware
        // (web group), which covers admin and driver routes alike.

        return $next($request);
    }
}
