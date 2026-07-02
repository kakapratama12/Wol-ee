<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\BotAiRequest;
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
            ->with(['users'])
            ->withCount([
                'users',
                'users as pengelola_count' => fn ($query) => $query->where('role', User::ROLE_PENGELOLA),
                'users as staff_count' => fn ($query) => $query->where('role', User::ROLE_STAFF),
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
                    'pengelola_count' => $tenant->pengelola_count,
                    'pengelola_users' => $tenant->users->where('role', User::ROLE_PENGELOLA)->values()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]),
                    'staff_count' => $tenant->staff_count,
                    'has_bot_token' => ! empty($tenant->bot_token),
                    'business_type' => $tenant->business_type ?? 'single',
                    'ai_usage_today' => $aiUsageToday,
                    'feedback_count' => $tenant->feedback_count,
                    'created_at' => $tenant->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Platform/Tenants', [
            'tenants' => $tenants,
        ]);
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:free,pro,business'],
            'pengelola_name' => ['required', 'string', 'max:255'],
            'pengelola_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'pengelola_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $slug = \Illuminate\Support\Str::slug($data['name']);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'plan' => $data['plan'],
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        User::create([
            'name' => $data['pengelola_name'],
            'email' => $data['pengelola_email'],
            'password' => $data['pengelola_password'],
            'role' => User::ROLE_PENGELOLA,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->route('platform.tenants')->with('success', 'Usaha berhasil dibuat.');
    }

    public function updateTenant(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'business_type' => ['required', 'string', 'in:single,multi'],
        ]);

        $tenant->update($data);

        return redirect()->route('platform.tenants')->with('success', 'Usaha berhasil diperbarui.');
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

    public function botSkills(): Response
    {
        $path = base_path('bot/skills.json');
        $skills = file_exists($path)
            ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)
            : [];

        $summary = [
            'total' => count($skills),
            'active' => collect($skills)->where('status', 'active')->count(),
            'planner_enabled' => collect($skills)->where('planner_enabled', true)->count(),
            'confirmation_required' => collect($skills)->where('confirmation_required', true)->count(),
        ];

        return Inertia::render('Platform/BotSkills', [
            'summary' => $summary,
            'skills' => $skills,
        ]);
    }

    public function aiUsage(): Response
    {
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $start = $now->copy()->subDays(6)->startOfDay();
        $requests = BotAiRequest::query()
            ->with('tenant:id,name,plan')
            ->where('requested_at', '>=', $start)
            ->get();
        $todayRequests = $requests->filter(fn (BotAiRequest $request) => $request->requested_at->isSameDay($now));

        $usageByTenant = Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) use ($today, $start, $requests, $todayRequests) {
                $tenantRequests = $requests->where('tenant_id', $tenant->id);
                $tenantTodayRequests = $todayRequests->where('tenant_id', $tenant->id);
                $quotaToday = (int) BotAiUsage::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereDate('usage_date', $today)
                    ->sum('count');
                $peakRpm = $tenantRequests
                    ->groupBy(fn (BotAiRequest $request) => $request->requested_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'))
                    ->map->count()
                    ->max() ?? 0;
                $errors = $tenantRequests->where('status', BotAiRequest::STATUS_ERROR)->count();
                $total = $tenantRequests->count();

                return [
                    'tenant_id' => $tenant->id,
                    'tenant' => $tenant->name,
                    'plan' => $tenant->plan,
                    'today' => $tenantTodayRequests->count(),
                    'quota_today' => $quotaToday,
                    'last_7_days' => $total,
                    'peak_rpm' => $peakRpm,
                    'error_rate' => $total > 0 ? round(($errors / $total) * 100, 1) : 0,
                    'tokens' => (int) $tenantRequests->sum('total_tokens'),
                ];
            });

        $daily = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = Carbon::now('Asia/Jakarta')->subDays($daysAgo)->toDateString();

            return [
                'date' => $date,
                'count' => (int) BotAiRequest::query()->whereDate('requested_at', $date)->count(),
                'errors' => (int) BotAiRequest::query()
                    ->whereDate('requested_at', $date)
                    ->where('status', BotAiRequest::STATUS_ERROR)
                    ->count(),
            ];
        });

        $byPlan = Tenant::query()
            ->select('plan')
            ->selectRaw('COUNT(*) as tenants')
            ->groupBy('plan')
            ->get()
            ->map(function ($row) use ($today, $requests) {
                $tenantIds = Tenant::query()->where('plan', $row->plan)->pluck('id');
                $planRequests = $requests->whereIn('tenant_id', $tenantIds);
                $provider = config("ai.plans.{$row->plan}.provider", 'groq');

                return [
                    'plan' => $row->plan,
                    'tenants' => (int) $row->tenants,
                    'usage_today' => (int) BotAiRequest::query()
                        ->whereIn('tenant_id', $tenantIds)
                        ->whereDate('requested_at', $today)
                        ->count(),
                    'quota_today' => (int) BotAiUsage::query()
                        ->whereIn('tenant_id', $tenantIds)
                        ->whereDate('usage_date', $today)
                        ->sum('count'),
                    'daily_quota' => (int) config("ai.plans.{$row->plan}.daily_ai_quota", 25),
                    'provider' => $provider,
                    'provider_rpm_limit' => (int) config("ai.providers.{$provider}.rpm_limit", 0),
                    'provider_rpd_limit' => (int) config("ai.providers.{$provider}.rpd_limit", 0),
                    'tokens' => (int) $planRequests->sum('total_tokens'),
                ];
            });

        $byProvider = collect(config('ai.providers'))
            ->map(function (array $config, string $provider) use ($requests, $todayRequests) {
                $providerRequests = $requests->where('provider', $provider);
                $providerTodayRequests = $todayRequests->where('provider', $provider);
                $peakRpm = $providerRequests
                    ->groupBy(fn (BotAiRequest $request) => $request->requested_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'))
                    ->map->count()
                    ->max() ?? 0;
                $errors = $providerRequests->where('status', BotAiRequest::STATUS_ERROR)->count();
                $total = $providerRequests->count();

                return [
                    'provider' => $provider,
                    'label' => $config['label'] ?? $provider,
                    'today' => $providerTodayRequests->count(),
                    'last_7_days' => $total,
                    'peak_rpm' => $peakRpm,
                    'rpm_limit' => (int) ($config['rpm_limit'] ?? 0),
                    'rpd_limit' => (int) ($config['rpd_limit'] ?? 0),
                    'error_rate' => $total > 0 ? round(($errors / $total) * 100, 1) : 0,
                    'tokens' => (int) $providerRequests->sum('total_tokens'),
                ];
            })
            ->values();

        return Inertia::render('Platform/AiUsage', [
            'summary' => [
                'today' => $todayRequests->count(),
                'quota_today' => (int) BotAiUsage::query()->whereDate('usage_date', $today)->sum('count'),
                'last_7_days' => $requests->count(),
                'peak_rpm' => $requests
                    ->groupBy(fn (BotAiRequest $request) => $request->requested_at->timezone('Asia/Jakarta')->format('Y-m-d H:i'))
                    ->map->count()
                    ->max() ?? 0,
                'error_rate' => $requests->count() > 0
                    ? round(($requests->where('status', BotAiRequest::STATUS_ERROR)->count() / $requests->count()) * 100, 1)
                    : 0,
                'tokens' => (int) $requests->sum('total_tokens'),
            ],
            'daily' => $daily,
            'byTenant' => $usageByTenant,
            'byPlan' => $byPlan,
            'byProvider' => $byProvider,
        ]);
    }

    public function users(): Response
    {
        $users = User::query()
            ->with('tenant:id,name')
            ->where('role', '!=', User::ROLE_SUPER_ADMIN)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'tenant' => $user->tenant?->name,
                'email_verified' => ! is_null($user->email_verified_at),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Platform/Users', [
            'users' => $users,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'roles' => [
                User::ROLE_PENGELOLA => 'Pengelola',
                User::ROLE_STAFF => 'Staff',
            ],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:pengelola,staff'],
            'tenant_id' => ['required', 'exists:tenants,id'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'tenant_id' => $data['tenant_id'],
        ]);

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:pengelola,staff'],
            'tenant_id' => ['required', 'exists:tenants,id'],
        ]);

        $user->update([
            'role' => $data['role'],
            'tenant_id' => $data['tenant_id'],
        ]);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => $data['password'],
        ]);

        return back()->with('success', 'Password berhasil direset.');
    }
}
