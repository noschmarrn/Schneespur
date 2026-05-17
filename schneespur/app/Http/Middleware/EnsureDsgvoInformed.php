<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDsgvoInformed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $requiredVersion = (int) Setting::get('dsgvo_template_version', 1);

        if ($user->dsgvo_informed_at === null || $user->confirmed_version < $requiredVersion) {
            return redirect('/onboarding/dsgvo');
        }

        return $next($request);
    }
}
