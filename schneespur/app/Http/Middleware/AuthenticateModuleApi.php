<?php

namespace App\Http\Middleware;

use App\Models\ModuleApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateModuleApi
{
    public function handle(Request $request, Closure $next, ?string $moduleSlug = null): Response
    {
        $moduleSlug = $moduleSlug ?? $request->route('module_slug');

        if ($bearerToken = $request->bearerToken()) {
            $tokenHash = hash('sha256', $bearerToken);

            $token = ModuleApiToken::where('token_hash', $tokenHash)
                ->valid()
                ->first();

            if ($token && $token->module_slug === $moduleSlug) {
                $token->forceFill(['last_used_at' => now()])->saveQuietly();

                $request->attributes->set('module_slug', $token->module_slug);
                $request->attributes->set('module_api_token_id', $token->id);

                Log::debug('Module API auth successful', [
                    'module_slug' => $token->module_slug,
                    'token_id' => $token->id,
                ]);

                return $next($request);
            }

            Log::warning('Module API auth failed: invalid token', [
                'module_slug' => $moduleSlug,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (Auth::check()) {
            $request->attributes->set('module_slug', $moduleSlug);

            Log::debug('Module API auth via session', [
                'module_slug' => $moduleSlug,
                'user_id' => Auth::id(),
            ]);

            return $next($request);
        }

        Log::warning('Module API auth failed: no credentials', [
            'module_slug' => $moduleSlug,
            'ip' => $request->ip(),
        ]);

        return response()->json(['error' => 'Unauthenticated'], 401);
    }
}
