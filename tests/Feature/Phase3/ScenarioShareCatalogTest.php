<?php

require_once __DIR__.'/StudioTestHelpers.php';

use App\Models\Agent;
use App\Models\Passport;
use App\Models\TenantAgentSetting;
use App\Services\Media\Drivers\FakeMediaDriver;
use App\Services\Media\MediaRegistry;
use App\Services\Studio\ScenarioService;
use App\Services\Studio\ShareLinkService;
use App\Services\Studio\ToolCatalog;

beforeEach(function () {
    $this->ahmad = studioSetup();
    app(MediaRegistry::class)->override('image', new FakeMediaDriver);
});

it('composes a scenario: scenes, strict reordering, text and image generation', function () {
    $service = app(ScenarioService::class);
    $scenario = $service->create($this->ahmad, workspaceFor($this->ahmad), 'رحلة قطرة ماء', ['topic' => 'دورة الماء', 'grade' => '4']);
    $a = $service->addScene($this->ahmad, $scenario, 'text', 'البداية', 'قصة قصيرة عن قطرة ماء');
    $b = $service->addScene($this->ahmad, $scenario, 'image', 'التبخّر', 'رسم للشمس والبحر');

    expect(fn () => $service->reorder($this->ahmad, $scenario, [$a['id']]))->toThrow(DomainException::class);
    $service->reorder($this->ahmad, $scenario, [$b['id'], $a['id']]);

    claudeReplies('كان في قطرة ماء صغيرة…');
    $service->generateScene($this->ahmad, $scenario->fresh(), $a['id']);
    $service->generateScene($this->ahmad, $scenario->fresh(), $b['id']);

    $scenes = collect($service->present($scenario->fresh()))->keyBy('id');
    expect($scenario->fresh()->content['scenes'][0]['id'])->toBe($b['id'])
        ->and($scenes[$a['id']]['body'])->toContain('قطرة')
        ->and($scenes[$b['id']]['status'])->toBe('ready');
});

it('shares read-only links publicly until revoked', function () {
    $scenario = app(ScenarioService::class)->create($this->ahmad, workspaceFor($this->ahmad), 'موقف', ['topic' => 'x']);
    [$link, $raw] = app(ShareLinkService::class)->create($this->ahmad, $scenario, 7);
    auth()->logout();
    bareeTenantContext()->clear();

    $this->get("/s/{$raw}")->assertOk();
    $this->get('/s/'.str_repeat('a', 48))->assertNotFound();

    bareeTenantContext()->set($this->ahmad->tenant_id);
    app(ShareLinkService::class)->revoke($this->ahmad, $link);
    bareeTenantContext()->clear();
    $this->get("/s/{$raw}")->assertNotFound();
});

it('shows each person only the tools of their role and institution, ranked by their gaps', function () {
    $catalog = app(ToolCatalog::class);
    $keys = $catalog->forUser($this->ahmad)->pluck('key');

    expect($keys)->toContain('lesson_pack')->toContain('meeting_minutes')
        ->not->toContain('cv_screening')->not->toContain('scenario_scene');

    $worksheet = bareeTenantContext()->asPlatform(fn () => Agent::where('key', 'worksheet')->first());
    TenantAgentSetting::updateOrCreate(['agent_id' => $worksheet->id], ['enabled' => false]);
    expect($catalog->forUser($this->ahmad)->pluck('key'))->not->toContain('worksheet');

    Passport::updateOrCreate(['user_id' => $this->ahmad->id], ['readiness' => 50, 'competencies' => ['productivity' => 20, 'prompting' => 25, 'data_analysis' => 90, 'content_creation' => 85]]);
    $top = $catalog->recommend($this->ahmad->fresh())->first();
    expect($top['reason'])->not->toBeEmpty()->and($top['agent']->key)->toBe('meeting_minutes');
});
