<?php

use App\AI\Gateway\AiGateway;
use App\Models\Agent;
use App\Models\Plan;
use App\Models\PromptBlock;
use App\Models\User;
use App\Models\UserAgentPreference;
use App\Models\UserConsent;
use App\Services\Institution\AnalyticsService;
use Database\Seeders\BareeSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['knowledge.embeddings.driver' => 'fake', 'broadcasting.default' => 'null', 'ai_gateway.providers.anthropic.key' => 'test']);
    $this->seed(BareeSeeder::class);
    $this->teacher = User::where('email', 'ahmad@future.test')->first();
    $this->staff = User::where('email', 'staff@baree.test')->first();
    bareeTenantContext()->set($this->teacher->tenant_id);
});

it('shows the full passport page', function () {
    $this->actingAs($this->teacher)->get('/passport')->assertOk();
});

it('lets the employee grant and revoke manager visibility, which analytics respects', function () {
    $this->teacher->tenant->update(['settings' => ['privacy' => ['mode' => 'consent']]]);
    \App\Models\Passport::create(['user_id' => $this->teacher->id, 'readiness' => 70]);
    $owner = userWithRole('institution_owner', $this->teacher->tenant);

    $this->actingAs($this->teacher)->put('/settings/privacy', ['key' => 'manager_visibility', 'granted' => true])->assertRedirect();
    expect(app(AnalyticsService::class)->individualLevel($owner, $this->teacher))->not->toBeNull();

    $this->actingAs($this->teacher)->put('/settings/privacy', ['key' => 'manager_visibility', 'granted' => false]);
    expect(app(AnalyticsService::class)->individualLevel($owner, $this->teacher->fresh()))->toBeNull()
        ->and(UserConsent::where('user_id', $this->teacher->id)->where('key', 'manager_visibility')->value('granted'))->toBeFalse();
});

it('exports a person\'s data and erases it on request', function () {
    $export = $this->actingAs($this->teacher)->get('/settings/privacy/export')->assertOk()->json();
    expect($export['profile']['email'])->toBe('ahmad@future.test');

    $this->actingAs($this->teacher)->delete('/settings/account', ['password' => 'password'])->assertRedirect('/');
    $erased = User::find($this->teacher->id);
    expect($erased->email)->toEndWith('@invalid.baree')->and($erased->status)->toBe('disabled')->and($erased->name)->toBe('مستخدم محذوف');
});

it('adds the user\'s personal instructions (layer 4) to every run of that agent', function () {
    $agent = bareeTenantContext()->asPlatform(fn () => Agent::where('key', 'worksheet')->first());
    $this->actingAs($this->teacher)->put('/studio/tools/worksheet/settings', ['instructions' => 'PERSONAL_RULE_42'])->assertRedirect();
    expect(UserAgentPreference::where('user_id', $this->teacher->id)->where('agent_id', $agent->id)->value('instructions'))->toBe('PERSONAL_RULE_42');

    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']], 'usage' => []])]);
    app(AiGateway::class)->run($this->teacher, 'worksheet', ['grade' => '8', 'subject' => 'رياضيات', 'topic' => 'x', 'items' => 3]);
    Http::assertSent(fn (Request $r) => str_contains(json_encode($r->data()), 'PERSONAL_RULE_42'));
});

it('validates question quality, versions prompt blocks and edits plans', function () {
    $competency = bareeTenantContext()->asPlatform(fn () => \App\Models\Competency::first());
    $bad = ['competency_id' => $competency->id, 'type' => 'scenario', 'difficulty' => 2, 'status' => 'draft', 'stem' => ['ar' => 'سؤال'],
        'options' => [['label' => 'أ', 'score' => 3], ['label' => 'ب', 'score' => 3], ['label' => 'ج', 'score' => 2]]];
    $this->actingAs($this->staff)->post('/platform/questions', $bad)->assertSessionHasErrors('options');

    $this->actingAs($this->staff)->post('/platform/prompts', ['key' => 'quality.verification', 'group' => 'quality', 'content' => 'v2'])->assertRedirect();
    $draft = bareeTenantContext()->asPlatform(fn () => PromptBlock::where('key', 'quality.verification')->orderByDesc('version')->first());
    $this->actingAs($this->staff)->post("/platform/prompts/{$draft->id}/publish")->assertRedirect();
    expect(bareeTenantContext()->asPlatform(fn () => PromptBlock::where('key', 'quality.verification')->where('status', 'published')->count()))->toBe(1);

    $plan = bareeTenantContext()->asPlatform(fn () => Plan::where('key', 'starter')->first());
    $this->actingAs($this->staff)->put("/platform/plans/{$plan->id}", ['name_ar' => 'أساسية', 'name_en' => 'Starter', 'price_per_seat_cents' => 500, 'currency' => 'usd',
        'interval' => 'year', 'limits' => ['users' => 200, 'monthly_credits' => 20000, 'custom_agents' => false, 'media' => false, 'api' => false], 'active' => true])->assertRedirect();
    expect(bareeTenantContext()->asPlatform(fn () => $plan->fresh()->currency))->toBe('USD');
});

it('rejects quiz answers that are not one of the options when authoring a lesson', function () {
    $this->actingAs($this->staff)->post('/platform/authoring/courses', ['key' => 'ai_for_hr', 'title' => 'AI للموارد البشرية', 'hours' => 2])->assertRedirect();
    $course = bareeTenantContext()->asPlatform(fn () => \App\Models\Course::where('key', 'ai_for_hr')->first());
    $this->actingAs($this->staff)->post("/platform/authoring/courses/{$course->id}/modules", ['title' => 'الأساسيات']);
    $module = bareeTenantContext()->asPlatform(fn () => $course->modules()->first());

    $this->actingAs($this->staff)->post("/platform/authoring/courses/{$course->id}/lessons", ['module_id' => $module->id, 'type' => 'quiz', 'title' => 'اختبار', 'minutes' => 5,
        'quiz' => [['q' => 'س', 'options' => ['أ', 'ب'], 'answer' => 5]]])->assertSessionHasErrors('quiz.0.answer');
});
