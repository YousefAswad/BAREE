<?php

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use App\Models\Agent;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use App\Models\UserAgentPreference;
use App\Services\CreditService;
use Database\Seeders\BareeSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    config([
        'ai_gateway.providers.anthropic.key' => 'test-anthropic',
        'ai_gateway.providers.openai.key' => 'test-openai',
        'ai_gateway.providers.openai.models.standard' => 'openai-test-model',
    ]);
    $this->ahmad = User::where('email', 'ahmad@future.test')->first();
    $this->ahmad->tenant->update(['settings' => ['privacy' => ['protected_names' => ['سلمى']]]]);
    bareeTenantContext()->set($this->ahmad->tenant_id);
    $this->inputs = ['grade' => 'الرابع', 'subject' => 'رياضيات', 'topic' => 'جمع الكسور مع سلمى', 'duration' => 45, 'student_level' => 'mixed'];
});

function anthropicReply(string $text): array
{
    return ['id' => 'msg_test', 'model' => 'claude-sonnet-5-5', 'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 1200, 'output_tokens' => 900]];
}

it('runs an agent through Claude, redacts names, meters credits and restores the output', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply('خطة الدرس لـ [PERSON_1]'))]);
    $before = app(CreditService::class)->balance($this->ahmad->tenant_id);

    $run = app(AiGateway::class)->run($this->ahmad, 'lesson_pack', $this->inputs);

    expect($run->status)->toBe('succeeded')->and($run->provider)->toBe('anthropic')
        ->and($run->redactions_count)->toBeGreaterThan(0)
        ->and(app(CreditService::class)->balance($this->ahmad->tenant_id))->toBe($before - 18)
        ->and(app(AiGateway::class)->presentOutput($run, $this->ahmad))->toBe('خطة الدرس لـ سلمى');

    Http::assertSent(function (Request $request) {
        $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE); // compare real characters, not \u escapes

        return $request->hasHeader('x-api-key', 'test-anthropic')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && ! str_contains($body, 'سلمى')
            && str_contains($body, '[PERSON_1]');
    });
});

it('fails over to the next provider when Claude is unavailable', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 500),
        'api.openai.com/*' => Http::response(['model' => 'openai-test-model', 'choices' => [['message' => ['content' => 'خطة'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 700]]),
    ]);

    $run = app(AiGateway::class)->run($this->ahmad, 'lesson_pack', $this->inputs);

    expect($run->provider)->toBe('openai')->and($run->attempts[0]['provider'])->toBe('anthropic');
});

it('refuses to run without credits and does not call any provider', function () {
    Http::fake();
    CreditLedgerEntry::create(['delta' => -app(CreditService::class)->balance($this->ahmad->tenant_id), 'reason' => 'adjustment', 'created_at' => now()]);

    expect(fn () => app(AiGateway::class)->run($this->ahmad, 'lesson_pack', $this->inputs))->toThrow(AiPolicyViolation::class);
    Http::assertNothingSent();
});

it('respects the agent customization policy for personal instructions', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicReply('ok'))]);
    $agent = bareeTenantContext()->asPlatform(fn () => Agent::where('key', 'lesson_pack')->first());
    UserAgentPreference::create(['user_id' => $this->ahmad->id, 'agent_id' => $agent->id, 'instructions' => 'SECRET_PREFERENCE']);

    bareeTenantContext()->asPlatform(fn () => $agent->publishedVersion->update(['customization_policy' => ['user' => ['instructions' => false]]]));
    bareeTenantContext()->set($this->ahmad->tenant_id);

    app(AiGateway::class)->run($this->ahmad, 'lesson_pack', $this->inputs);

    Http::assertSent(fn (Request $request) => ! str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'SECRET_PREFERENCE'));
});

it('rejects invalid guided-form inputs before spending anything', function () {
    Http::fake();

    expect(fn () => app(AiGateway::class)->run($this->ahmad, 'lesson_pack', ['topic' => 'x']))->toThrow(AiPolicyViolation::class);
    Http::assertNothingSent();
});
