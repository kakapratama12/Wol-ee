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
            // Allow pengelola (owner) from single-outlet businesses to access POS
            if ($user && $user->isPengelola()) {
                $isSingleOutlet = $user->tenant && $user->tenant->business_type === 'single';

                if ($isSingleOutlet) {
                    return $next($request);
                }

                return redirect()
                    ->route('dashboard')
                    ->with('error', 'POS hanya untuk akun staff.');
            }

            if ($request->expectsJson()) {
                abort(403, 'Hanya staff yang dapat mengakses POS.');
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
