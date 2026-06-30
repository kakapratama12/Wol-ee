<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isPengelola()) {
            abort(403, 'Hanya Pengelola yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
