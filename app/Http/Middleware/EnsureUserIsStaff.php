<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isStaff()) {
            if ($user && $user->isPengelola()) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'POS hanya untuk akun staff. Gunakan /pos/login untuk staff.');
            }

            if ($request->expectsJson()) {
                abort(403, 'Hanya staff yang dapat mengakses POS.');
            }

            return redirect()->route('pos.login');
        }

        return $next($request);
    }
}
