<?php

namespace App\Http\Middleware;

use App\Support\KioskoState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskoSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! KioskoState::empleado()) {
            return redirect()->route('kiosko.cedula');
        }

        return $next($request);
    }
}
