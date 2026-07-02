<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCashierToPos
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStaff() && ! $request->is('pos', 'pos/*', 'logout')) {
            return redirect()->route('pos.landing');
        }

        return $next($request);
    }
}
