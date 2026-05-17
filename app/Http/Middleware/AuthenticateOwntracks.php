<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOwntracks
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->getUser();
        $password = $request->getPassword();

        if ($username === null || $password === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::where('owntracks_username', $username)->first();

        if (! $user || ! Hash::check($password, $user->owntracks_password_hash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
