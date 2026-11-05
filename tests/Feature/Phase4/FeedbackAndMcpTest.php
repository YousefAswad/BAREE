<?php

require_once __DIR__.'/Phase4Helpers.php';

use App\AI\Gateway\AiGateway;
use App\Models\OutputFeedback;
use App\Models\UserConsent;
use App\Services\Learning\FeedbackService;

beforeEach(fn () => $this->ahmad = learningSetup());

function aRun($user)
{
    claudeJson('## ورقة عمل');

    return app(AiGateway::class)->run($user, 'worksheet', ['grade' => '8', 'subject' => 'رياضيات', 'topic' => 'المعادلات', 'items' => 5]);
}

it('records ratings with redacted comments and respects the improvement consent', function () {
    app(\App\Services\Institution\ProtectedTermsRepository::class)->add(['رهف']);
    $run = aRun($this->ahmad);

    $feedback = app(FeedbackService::class)->rate($this->ahmad, $run, 2, ['too_long', 'hacked_reason'], 'طويلة على رهف');
    expect($feedback->reasons)->toBe(['too_long'])->and($feedback->comment)->not->toContain('رهف')->and($feedback->share_with_baree)->toBeTrue();

    UserConsent::create(['user_id' => $this->ahmad->id, 'key' => 'improve_agents', 'granted' => false, 'decided_at' => now()]);
    $private = app(FeedbackService::class)->rate($this->ahmad, $run, 5);
    expect($private->share_with_baree)->toBeFalse();

    $summary = collect(app(FeedbackService::class)->platformSummary())->firstWhere('agent', 'worksheet');
    expect($summary['ratings'])->toBe(1)->and($summary['average'])->toEqual(2); // the opted-out rating is excluded
});

it('turns manual edits of AI outputs into an implicit signal', function () {
    $run = aRun($this->ahmad);
    $workspace = app(\App\Services\Studio\WorkspaceService::class)->create($this->ahmad, ['kind' => 'project', 'title' => 'x']);
    $output = app(\App\Services\Studio\OutputService::class)->saveFromRun($run, $this->ahmad, $workspace, 'worksheet', 'ورقة');

    app(\App\Services\Studio\OutputService::class)->newVersion($output, $this->ahmad, '## ورقة عمل معدّلة بالكامل مع أسئلة جديدة');

    expect(OutputFeedback::where('rating', 0)->first()->edit_distance)->toBeGreaterThan(20);
});

it('only lets personal tokens with the mcp ability use the MCP tools', function () {
    if (! class_exists(\Laravel\Mcp\Server::class) || ! class_exists(\Laravel\Sanctum\Sanctum::class)) {
        $this->markTestSkipped('composer require laravel/mcp laravel/sanctum');
    }
    $probe = new class { use \App\Mcp\Tools\McpAuth; public function check() { return $this->mcpUser(); } };

    \Laravel\Sanctum\Sanctum::actingAs($this->ahmad, ['other']);
    expect(fn () => $probe->check())->toThrow(RuntimeException::class);

    \Laravel\Sanctum\Sanctum::actingAs($this->ahmad, ['mcp']);
    expect($probe->check()->id)->toBe($this->ahmad->id);
});
