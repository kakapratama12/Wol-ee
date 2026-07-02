<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('pos')
                ->name('pos.')
                ->group(base_path('routes/pos.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->user()?->isStaff()) {
                return route('pos.landing');
            }

            if ($request->user()?->isSuperAdmin()) {
                return route('platform.overview');
            }

            return route('dashboard');
        });

        $middleware->web(append: [
            HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'owner' => \App\Http\Middleware\EnsureUserIsOwner::class,
            'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
            'pos.session' => \App\Http\Middleware\EnsurePosSessionOpen::class,
            'super_admin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
            'bot.token' => \App\Http\Middleware\BotTokenAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
