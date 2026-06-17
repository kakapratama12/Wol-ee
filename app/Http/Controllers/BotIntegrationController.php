<?php

namespace App\Http\Controllers;

use App\Services\BotTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotIntegrationController extends Controller
{
    public function __construct(private readonly BotTokenService $botTokens) {}

    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Settings/BotIntegration', [
            'hasToken' => ! empty($tenant->bot_token),
            'tenantName' => $tenant->name,
            'plainToken' => session('bot_token_plain'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $plainToken = $this->botTokens->generate($tenant);

        return back()->with([
            'success' => 'Token bot berhasil dibuat. Salin sekarang — tidak ditampilkan lagi.',
            'bot_token_plain' => $plainToken,
        ]);
    }
}
