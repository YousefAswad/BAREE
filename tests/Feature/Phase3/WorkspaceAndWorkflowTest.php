<?php

require_once __DIR__.'/StudioTestHelpers.php';

use App\Models\Output;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Services\CreditService;
use App\Services\Studio\WorkflowRunner;
use App\Services\Studio\WorkspaceService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->ahmad = studioSetup();
    $this->colleague = User::where('email', 'admin@future.test')->first();
    $this->service = app(WorkspaceService::class);
});

it('keeps workspaces private until shared, and children inherit sharing', function () {
    $course = workspaceFor($this->ahmad);
    $unit = $this->service->create($this->ahmad, ['kind' => 'unit', 'title' => 'الوحدة ١', 'parent_id' => $course->id]);

    $this->actingAs($this->colleague)->get("/workspaces/{$unit->id}")->assertForbidden();

    $this->service->share($this->ahmad, $unit, $this->colleague, 'viewer');
    $this->actingAs($this->colleague)->get("/workspaces/{$unit->id}")->assertOk();
    $this->actingAs($this->colleague)->patch("/workspaces/{$unit->id}", ['title' => 'x'])->assertForbidden();
});

it('enforces the course → unit → lesson structure and blocks sharing outside the institution', function () {
    $course = workspaceFor($this->ahmad);
    $outsider = User::factory()->create();

    expect(fn () => $this->service->create($this->ahmad, ['kind' => 'lesson', 'title' => 'x', 'parent_id' => $course->id]))->toThrow(DomainException::class)
        ->and(fn () => $this->service->share($this->ahmad, $course, $outsider, 'editor'))->toThrow(DomainException::class);
});

it('runs the lesson-pack workflow step by step, saves each component and charges per step', function () {
    $workspace = workspaceFor($this->ahmad);
    claudeReplies('## الخطة', '## الشرائح', '## النشاط', '## ورقة العمل', '## الاختبار', '## الواجب');
    $before = app(CreditService::class)->balance($this->ahmad->tenant_id);

    $run = app(WorkflowRunner::class)->start($this->ahmad, 'lesson_pack', ['grade' => '8', 'subject' => 'رياضيات', 'topic' => 'المعادلات', 'duration' => 45, 'student_level' => 'mixed'], $workspace, null, []);
    $run = WorkflowRun::find($run->id); // the queued job ran synchronously in tests

    expect($run->status)->toBe('succeeded')
        ->and(collect($run->steps)->pluck('status')->unique()->all())->toBe(['succeeded'])
        ->and(Output::where('workspace_id', $workspace->id)->count())->toBe(6)
        ->and(app(CreditService::class)->balance($this->ahmad->tenant_id))->toBe($before - 18);
    Http::assertSentCount(6);
});

it('stops the chain on a failed step, marks it partial, and regenerates one component as a new version', function () {
    $workspace = workspaceFor($this->ahmad);
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()->push(['content' => [['type' => 'text', 'text' => '## الخطة']], 'usage' => []])->push(['error' => 'x'], 500)->push(['content' => [['type' => 'text', 'text' => '## خطة أقصر']], 'usage' => []]),
        'api.openai.com/*' => Http::response(['error' => 'x'], 500),
    ]);

    $run = app(WorkflowRunner::class)->start($this->ahmad, 'lesson_pack', ['grade' => '8', 'subject' => 'رياضيات', 'topic' => 'المعادلات', 'duration' => 45, 'student_level' => 'mixed'], $workspace, null, []);
    $run = WorkflowRun::find($run->id);

    expect($run->status)->toBe('partial')
        ->and(collect($run->steps)->pluck('status')->all())->toBe(['succeeded', 'failed', 'skipped', 'skipped', 'skipped', 'skipped']);

    app(WorkflowRunner::class)->regenerate($run, 'plan', 'اختصر', $this->ahmad);
    $planOutput = Output::find(WorkflowRun::find($run->id)->steps[0]['output_id']);
    expect($planOutput->version)->toBe(2)->and($planOutput->content['body'])->toBe('## خطة أقصر');
});
