<?php

use App\Models\BotAiRequest;
use App\Models\BotAiUsage;
use App\Models\BotFeedback;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function superAdmin(): User
{
    return User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPER_ADMIN,
        'email_verified_at' => now(),
    ]);
}

it('menolak non super admin dari platform panel', function () {
    $this->actingAs(authenticateTestTenant('owner'));

    $this->get('/platform')->assertForbidden();
});

it('super admin bisa melihat platform overview tenant feedback dan usage', function () {
    $tenant = Tenant::factory()->create(['name' => 'A Kafe Demo', 'plan' => Tenant::PLAN_PRO]);
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_OWNER]);
    BotFeedback::create([
        'tenant_id' => $tenant->id,
        'telegram_user_id' => 123,
        'feedback_text' => 'bandingin profit bulan ini vs bulan lalu',
        'status' => BotFeedback::STATUS_NEW,
    ]);
    BotAiUsage::create([
        'tenant_id' => $tenant->id,
        'telegram_user_id' => 123,
        'usage_date' => now('Asia/Jakarta')->toDateString(),
        'count' => 7,
    ]);
    BotAiRequest::create([
        'tenant_id' => $tenant->id,
        'telegram_user_id' => 123,
        'plan' => Tenant::PLAN_PRO,
        'provider' => 'openrouter',
        'model' => 'deepseek/deepseek-chat',
        'status' => BotAiRequest::STATUS_SUCCESS,
        'latency_ms' => 850,
        'prompt_tokens' => 100,
        'completion_tokens' => 40,
        'total_tokens' => 140,
        'requested_at' => now('Asia/Jakarta'),
    ]);

    $this->actingAs(superAdmin());

    $this->get('/platform')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Overview')
            ->where('stats.newFeedbackCount', 1)
            ->where('stats.aiUsageToday', 7)
        );

    $this->get('/platform/tenants')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants')
            ->where('tenants.0.name', 'A Kafe Demo')
            ->where('tenants.0.ai_usage_today', 7)
            ->where('tenants.0.feedback_count', 1)
        );

    $this->get('/platform/feedback')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Feedback')
            ->where('feedback.data.0.feedback_text', 'bandingin profit bulan ini vs bulan lalu')
        );

    $this->get('/platform/ai-usage')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/AiUsage')
            ->where('summary.today', 1)
            ->where('summary.quota_today', 7)
            ->where('byProvider.1.provider', 'openrouter')
            ->where('byTenant.0.tokens', 140)
        );

    $this->get('/platform/bot-skills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/BotSkills')
            ->where('summary.active', 8)
            ->where('skills.0.name', 'record_sale')
            ->where('skills.0.confirmation_required', true)
        );
});

it('super admin bisa mengupdate status feedback', function () {
    $tenant = Tenant::factory()->create();
    $feedback = BotFeedback::create([
        'tenant_id' => $tenant->id,
        'telegram_user_id' => 123,
        'feedback_text' => 'mau compare bulan lalu',
        'status' => BotFeedback::STATUS_NEW,
    ]);

    $this->actingAs(superAdmin());

    $this->put('/platform/feedback/'.$feedback->id, [
        'status' => BotFeedback::STATUS_PLANNED,
        'note' => 'Masuk kandidat Sprint 5B',
    ])->assertRedirect();

    $this->assertDatabaseHas('bot_feedbacks', [
        'id' => $feedback->id,
        'status' => BotFeedback::STATUS_PLANNED,
        'note' => 'Masuk kandidat Sprint 5B',
    ]);
});

it('command bootstrap membuat super admin', function () {
    $this->artisan('wol-ee:create-super-admin', [
        '--email' => 'platform@wol-ee.local',
        '--password' => 'super-secret-12345',
        '--name' => 'Platform Admin',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'platform@wol-ee.local')->first();

    expect($user)
        ->not->toBeNull()
        ->and($user->tenant_id)->toBeNull()
        ->and($user->role)->toBe(User::ROLE_SUPER_ADMIN)
        ->and(Hash::check('super-secret-12345', $user->password))->toBeTrue();
});
