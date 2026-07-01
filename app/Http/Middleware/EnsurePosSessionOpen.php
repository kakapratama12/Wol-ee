<?php

namespace App\Http\Middleware;

use App\Services\CashierSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosSessionOpen
{
    public function __construct(private readonly CashierSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessions->findOpenSession($request->user());

        if (! $session) {
            return redirect()->route('pos.session.open.form');
        }

        return $next($request);
    }
}
