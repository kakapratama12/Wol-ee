<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\BotAiUsage;
use App\Models\BotFeedback;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    public function overview(): Response
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $tenantCount = Tenant::query()->count();
        $activeTenantCount = Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->count();
        $userCount = User::query()->where('role', '!=', User::ROLE_SUPER_ADMIN)->count();
        $newFeedbackCount = BotFeedback::query()->where('status', BotFeedback::STATUS_NEW)->count();
        $aiUsageToday = (int) BotAiUsage::query()->whereDate('usage_date', $today)->sum('count');

        $recentFeedback = BotFeedback::query()
            ->with('tenant:id,name,plan')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (BotFeedback $feedback) => [
                'id' => $feedback->id,
                'tenant' => $feedback->tenant?->name,
                'feedback_text' => $feedback->feedback_text,
                'status' => $feedback->status,
                'created_at' => $feedback->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Platform/Overview', [
            'stats' => compact('tenantCount', 'activeTenantCount', 'userCount', 'newFeedbackCount', 'aiUsageToday'),
            'recentFeedback' => $recentFeedback,
        ]);
    }

    public function tenants(): Response
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $tenants = Tenant::query()
            ->withCount([
                'users',
                'users as owner_count' => fn ($query) => $query->where('role', User::ROLE_OWNER),
                'users as admin_count' => fn ($query) => $query->where('role', User::ROLE_ADMIN),
                'feedback as feedback_count',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) use ($today) {
                $aiUsageToday = (int) BotAiUsage::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereDate('usage_date', $today)
                    ->sum('count');

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan' => $tenant->plan,
                    'status' => $tenant->status,
                    'users_count' => $tenant->users_count,
                    'owner_count' => $tenant->owner_count,
                    'admin_count' => $tenant->admin_count,
                    'has_bot_token' => ! empty($tenant->bot_token),
                    'ai_usage_today' => $aiUsageToday,
                    'feedback_count' => $tenant->feedback_count,
                    'created_at' => $tenant->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Platform/Tenants', [
            'tenants' => $tenants,
        ]);
    }

    public function feedback(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $tenantId = $request->integer('tenant_id') ?: null;

        $feedback = BotFeedback::query()
            ->with('tenant:id,name,plan')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BotFeedback $row) => [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'tenant' => $row->tenant?->name,
                'telegram_user_id' => $row->telegram_user_id,
                'original_message' => $row->original_message,
                'feedback_text' => $row->feedback_text,
                'status' => $row->status,
                'note' => $row->note,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Platform/Feedback', [
            'feedback' => $feedback,
            'filters' => [
                'status' => $status,
                'tenant_id' => $tenantId,
            ],
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => [
                BotFeedback::STATUS_NEW,
                BotFeedback::STATUS_REVIEWED,
                BotFeedback::STATUS_PLANNED,
                BotFeedback::STATUS_SHIPPED,
                BotFeedback::STATUS_REJECTED,
            ],
        ]);
    }

    public function updateFeedback(Request $request, BotFeedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                BotFeedback::STATUS_NEW,
                BotFeedback::STATUS_REVIEWED,
                BotFeedback::STATUS_PLANNED,
                BotFeedback::STATUS_SHIPPED,
                BotFeedback::STATUS_REJECTED,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback->update([
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Feedback diperbarui.');
    }

    public function aiUsage(): Response
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $start = Carbon::now('Asia/Jakarta')->subDays(6)->startOfDay();

        $usageByTenant = Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) use ($today, $start) {
                $todayCount = (int) BotAiUsage::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereDate('usage_date', $today)
                    ->sum('count');
                $last7Days = (int) BotAiUsage::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereDate('usage_date', '>=', $start->toDateString())
                    ->sum('count');

                return [
                    'tenant_id' => $tenant->id,
                    'tenant' => $tenant->name,
                    'plan' => $tenant->plan,
                    'today' => $todayCount,
                    'last_7_days' => $last7Days,
                ];
            });

        $daily = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = Carbon::now('Asia/Jakarta')->subDays($daysAgo)->toDateString();

            return [
                'date' => $date,
                'count' => (int) BotAiUsage::query()->whereDate('usage_date', $date)->sum('count'),
            ];
        });

        $byPlan = Tenant::query()
            ->select('plan')
            ->selectRaw('COUNT(*) as tenants')
            ->groupBy('plan')
            ->get()
            ->map(function ($row) use ($today) {
                $tenantIds = Tenant::query()->where('plan', $row->plan)->pluck('id');

                return [
                    'plan' => $row->plan,
                    'tenants' => (int) $row->tenants,
                    'usage_today' => (int) BotAiUsage::query()
                        ->whereIn('tenant_id', $tenantIds)
                        ->whereDate('usage_date', $today)
                        ->sum('count'),
                ];
            });

        return Inertia::render('Platform/AiUsage', [
            'summary' => [
                'today' => (int) BotAiUsage::query()->whereDate('usage_date', $today)->sum('count'),
                'last_7_days' => (int) BotAiUsage::query()->whereDate('usage_date', '>=', $start->toDateString())->sum('count'),
            ],
            'daily' => $daily,
            'byTenant' => $usageByTenant,
            'byPlan' => $byPlan,
        ]);
    }
}
