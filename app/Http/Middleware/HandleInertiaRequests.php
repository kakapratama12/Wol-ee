<?php

namespace App\Http\Middleware;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return md5(date('Y-m-d-H'));
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'businessType' => $request->user()?->tenant?->business_type ?? 'single',
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'pos_cart_error' => $request->session()->get('pos_cart_error'),
            ],
            'hasInvoices' => fn () => $request->user()
                ? Invoice::query()->exists()
                : false,
        ];
    }
}
