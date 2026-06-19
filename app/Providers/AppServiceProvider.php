<?php

namespace App\Providers;

use App\Support\TelegramNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramNotifier::class, function ($app) {
            $config = $app['config']['services.telegram'] ?? [];

            return new TelegramNotifier(
                $config['bot_token'] ?? null,
                $config['alert_chat_id'] ?? null,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('bot', function (Request $request) {
            $tenantId = $request->attributes->get('tenant')?->id;

            return Limit::perMinute(60)->by($tenantId ?: $request->ip());
        });
    }
}
