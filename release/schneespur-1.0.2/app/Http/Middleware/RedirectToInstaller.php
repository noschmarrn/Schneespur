<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('app/installed.lock'))) {
            return $next($request);
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'database.default' => 'sqlite',
        ]);

        if (! $request->is('install', 'install/*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
