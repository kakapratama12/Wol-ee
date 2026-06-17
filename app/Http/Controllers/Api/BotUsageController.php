<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Tenant;
use App\Services\BotUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotUsageController extends Controller
{
    public function __construct(private readonly BotUsageService $usage) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return ApiResponse::success('Kuota AI bot.', $this->usage->status(
            $tenant,
            (int) $data['telegram_user_id'],
        ));
    }

    public function consume(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $result = $this->usage->consume($tenant, (int) $data['telegram_user_id']);

        if (! $result['consumed']) {
            return ApiResponse::error(
                'Kuota AI hari ini habis. Reset besok jam 00:00 WIB. Upgrade ke Pro untuk kuota lebih besar.',
                'AI_QUOTA_EXCEEDED',
                429,
                [
                    'used' => $result['used'],
                    'limit' => $result['limit'],
                    'remaining' => 0,
                ],
            );
        }

        return ApiResponse::success('Kuota AI digunakan.', $result);
    }
}
