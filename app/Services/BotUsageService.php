<?php

namespace App\Services;

use App\Models\BotAiUsage;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BotUsageService
{
    public const LIMIT_FREE = 25;

    public const LIMIT_PRO = 150;

    public function dailyLimit(Tenant $tenant): int
    {
        return match ($tenant->plan) {
            Tenant::PLAN_PRO, Tenant::PLAN_BUSINESS => self::LIMIT_PRO,
            default => self::LIMIT_FREE,
        };
    }

    public function usesPremiumLlm(Tenant $tenant): bool
    {
        return in_array($tenant->plan, [Tenant::PLAN_PRO, Tenant::PLAN_BUSINESS], true);
    }

    /**
     * @return array{used: int, limit: int, remaining: int, plan: string, uses_premium_llm: bool, resets_at: string}
     */
    public function status(Tenant $tenant, int $telegramUserId, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now('Asia/Jakarta');
        $usageDate = $now->toDateString();
        $limit = $this->dailyLimit($tenant);
        $used = $this->currentCount($tenant, $telegramUserId, $usageDate);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'plan' => $tenant->plan,
            'uses_premium_llm' => $this->usesPremiumLlm($tenant),
            'resets_at' => $now->copy()->addDay()->startOfDay()->toIso8601String(),
        ];
    }

    /**
     * @return array{consumed: bool, used: int, limit: int, remaining: int}
     */
    public function consume(Tenant $tenant, int $telegramUserId, ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now('Asia/Jakarta');
        $usageDate = $now->toDateString();
        $limit = $this->dailyLimit($tenant);

        return DB::transaction(function () use ($tenant, $telegramUserId, $usageDate, $limit) {
            $row = BotAiUsage::query()
                ->where('tenant_id', $tenant->id)
                ->where('telegram_user_id', $telegramUserId)
                ->whereDate('usage_date', $usageDate)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = BotAiUsage::create([
                    'tenant_id' => $tenant->id,
                    'telegram_user_id' => $telegramUserId,
                    'usage_date' => $usageDate,
                    'count' => 0,
                ]);
            }

            if ($row->count >= $limit) {
                return [
                    'consumed' => false,
                    'used' => $row->count,
                    'limit' => $limit,
                    'remaining' => 0,
                ];
            }

            $row->increment('count');

            return [
                'consumed' => true,
                'used' => $row->fresh()->count,
                'limit' => $limit,
                'remaining' => max(0, $limit - $row->count),
            ];
        });
    }

    private function currentCount(Tenant $tenant, int $telegramUserId, string $usageDate): int
    {
        return (int) BotAiUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('telegram_user_id', $telegramUserId)
            ->whereDate('usage_date', $usageDate)
            ->value('count');
    }
}
