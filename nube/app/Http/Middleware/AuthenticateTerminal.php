<?php

namespace App\Http\Middleware;

use App\Models\AccesoTerminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTerminal
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['ok' => false, 'error' => 'token'], 401);
        }

        $terminal = AccesoTerminal::query()
            ->where('api_token', $token)
            ->where('activo', true)
            ->first();

        if (! $terminal) {
            return response()->json(['ok' => false, 'error' => 'token'], 401);
        }

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }
}
