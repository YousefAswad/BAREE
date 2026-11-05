<?php

require_once __DIR__.'/StudioTestHelpers.php';

use App\AI\Data\LlmRequest;
use App\AI\Drivers\AnthropicDriver;
use App\AI\Drivers\OpenAiCompatibleDriver;
use App\AI\Gateway\AiGateway;
use App\Models\KnowledgeFile;
use App\Services\Institution\ProtectedTermsRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->ahmad = studioSetup());

$inputs = ['grade' => 'الثامن', 'subject' => 'رياضيات', 'topic' => 'المعادلات', 'duration' => 45, 'student_level' => 'mixed'];

it('sends workspace documents marked "always", redacted, and ignores files the user cannot access', function () use ($inputs) {
    $workspace = workspaceFor($this->ahmad);
    app(ProtectedTermsRepository::class)->add(['رهف']);
    $mine = KnowledgeFile::create(['owner_type' => $workspace->getMorphClass(), 'owner_id' => $workspace->id, 'disk' => 'local', 'path' => 'x', 'original_name' => 'دليل.docx',
        'mime' => 'text/plain', 'size' => 1, 'sha256' => 'a', 'usage_mode' => 'always', 'status' => 'indexed', 'extracted_text' => 'ملاحظة: رهف بحاجة دعم بالكسور']);
    $other = bareeTenantContext()->runAs($this->ahmad->tenant_id, fn () => KnowledgeFile::create(['owner_type' => (new App\Models\User)->getMorphClass(), 'owner_id' => 999999,
        'disk' => 'local', 'path' => 'y', 'original_name' => 'سري.txt', 'mime' => 'text/plain', 'size' => 1, 'sha256' => 'b', 'status' => 'indexed', 'extracted_text' => 'SECRET_OTHER']));
    claudeReplies('خطة');

    app(AiGateway::class)->run($this->ahmad, 'lesson_pack', $inputs, $workspace, null, [$other->id]);

    Http::assertSent(function (Request $r) {
        $body = json_encode($r->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($body, 'دليل.docx') && ! str_contains($body, 'رهف') && ! str_contains($body, 'SECRET_OTHER');
    });
});

it('streams Claude deltas and still returns usage', function () {
    $sse = "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"model\":\"m\",\"usage\":{\"input_tokens\":12}}}\n\n"
        ."data: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"مرحبا \"}}\n\n"
        ."data: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"بالصف\"}}\n\n"
        ."data: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":7}}\n\n";
    Http::fake(['api.anthropic.com/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
    $pieces = [];

    $response = (new AnthropicDriver('k'))->stream(new LlmRequest('s', [['role' => 'user', 'content' => 'x']], 'm'), function ($p) use (&$pieces) { $pieces[] = $p; });

    expect($pieces)->toBe(['مرحبا ', 'بالصف'])->and($response->text)->toBe('مرحبا بالصف')
        ->and($response->inputTokens)->toBe(12)->and($response->outputTokens)->toBe(7);
});

it('streams OpenAI-compatible chunks including the final usage chunk', function () {
    $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"أ\"}}]}\n\ndata: {\"choices\":[{\"delta\":{\"content\":\"ب\"},\"finish_reason\":\"stop\"}]}\n\n"
        ."data: {\"choices\":[],\"usage\":{\"prompt_tokens\":5,\"completion_tokens\":2}}\n\ndata: [DONE]\n\n";
    Http::fake(['api.openai.com/*' => Http::response($sse, 200)]);

    $response = (new OpenAiCompatibleDriver('openai', 'k'))->stream(new LlmRequest('s', [], 'm'), fn () => null);

    expect($response->text)->toBe('أب')->and($response->outputTokens)->toBe(2)->and($response->stopReason)->toBe('stop');
});
