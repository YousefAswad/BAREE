<?php

use App\AI\VersionResolver;
use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\AgentEvaluationService;
use App\Services\Platform\AgentVersionService;
use Database\Seeders\BareeSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    config(['ai_gateway.providers.anthropic.key' => 'test']);
    $this->staff = User::where('email', 'staff@baree.test')->first();
    bareeTenantContext()->asPlatform(fn () => null);
    $this->agent = bareeTenantContext()->asPlatform(fn () => Agent::where('key', 'lesson_pack')->first());
    $this->service = app(AgentVersionService::class);
});

function claudeSays(string $text): void
{
    Http::fake(['api.anthropic.com/*' => Http::response(['model' => 'claude-sonnet-5-5', 'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]])]);
}

it('clones the published version into an editable draft and protects non-drafts', function () {
    $draft = bareeTenantContext()->asPlatform(fn () => $this->service->createDraft($this->agent, $this->staff));

    expect($draft->version)->toBe(2)->and($draft->status)->toBe('draft')
        ->and($draft->system_prompt)->toBe($this->agent->publishedVersion->system_prompt)
        ->and(fn () => $this->service->updateDraft($this->agent->publishedVersion, ['credits_cost' => 1]))->toThrow(DomainException::class)
        ->and(fn () => $this->service->updateDraft($draft, ['input_schema' => ['fields' => [['key' => 'Bad Key', 'type' => 'text']]]]))->toThrow(DomainException::class);
});

it('blocks rollout until every blocking test passes, then serves pilots only', function () {
    $draft = bareeTenantContext()->asPlatform(fn () => $this->service->createDraft($this->agent, $this->staff));
    $this->service->submitForTesting($draft);

    expect(fn () => $this->service->startRollout($draft, ['mode' => 'pilot', 'tenant_ids' => [1]]))->toThrow(DomainException::class);

    claudeSays(str_repeat('## الأهداف [PERSON_1] 45 40 ', 60)); // satisfies the seeded blocking cases
    $summary = app(AgentEvaluationService::class)->run($draft);
    expect($summary['blocking_failed'])->toBe(0);

    [$pilot, $other] = [Tenant::factory()->create(), Tenant::factory()->create()];
    $this->service->startRollout($draft->fresh(), ['mode' => 'pilot', 'tenant_ids' => [$pilot->id]]);

    $resolver = app(VersionResolver::class);
    $agent = $this->agent->fresh();
    expect($resolver->forTenant($agent, $pilot->id)->id)->toBe($draft->id)
        ->and($resolver->forTenant($agent, $other->id)->version)->toBe(1);
});

it('promotes to everyone and can roll back to the retired version', function () {
    $draft = bareeTenantContext()->asPlatform(fn () => $this->service->createDraft($this->agent, $this->staff));
    $this->service->submitForTesting($draft);
    claudeSays(str_repeat('## الأهداف [PERSON_1] 45 40 ', 60));
    app(AgentEvaluationService::class)->run($draft);

    $this->service->startRollout($draft->fresh(), ['mode' => 'all']);
    $agent = $this->agent->fresh();
    expect($agent->published_version_id)->toBe($draft->id);

    $v1 = $agent->versions()->where('version', 1)->first();
    expect($v1->status)->toBe('retired');

    $this->service->rollback($agent, $v1);
    expect($agent->fresh()->published_version_id)->toBe($v1->id)->and($draft->fresh()->status)->toBe('retired');
});

it('records failed assertions without crashing the batch', function () {
    $draft = bareeTenantContext()->asPlatform(fn () => $this->service->createDraft($this->agent, $this->staff));
    claudeSays('قصير');

    $summary = app(AgentEvaluationService::class)->run($draft);

    expect($summary['failed'])->toBeGreaterThan(0)
        ->and(app(AgentEvaluationService::class)->latestBatchPasses($draft))->toBeFalse();
});
