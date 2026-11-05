<?php

require_once __DIR__.'/Phase4Helpers.php';

use App\Models\Mission;
use App\Models\Passport;
use App\Models\UserBadge;
use App\Models\XpEvent;
use App\Services\Learning\GamificationService;
use App\Services\Learning\MissionService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->ahmad = learningSetup();
    $this->mission = Mission::where('key', 'exam_error_hunt')->first();
    $this->service = app(MissionService::class);
});

$coach = fn (int $score, bool $ready = true) => json_encode(['scores' => ['clarity' => $score, 'privacy' => $score, 'verification' => $score, 'improvement' => $score], 'feedback' => 'ملاحظة', 'hint' => null, 'ready' => $ready], JSON_UNESCAPED_UNICODE);

it('never sends the planted answers to the browser', function () {
    $page = $this->actingAs($this->ahmad)->get('/lab/missions/exam_error_hunt')->viewData('page');

    expect(json_encode($page['props'], JSON_UNESCAPED_UNICODE))->not->toContain('answers')->toContain('طالب 1');
});

it('keeps a step open when the objective check fails, whatever the coach says', function () use ($coach) {
    claudeJson($coach(90), $coach(90), $coach(90, true));
    $attempt = $this->service->start($this->ahmad, $this->mission);
    $this->service->submit($this->ahmad, $attempt, 'سؤالي: شو أصعب سؤال؟');
    $this->service->submit($this->ahmad, $attempt->fresh(), 'حلّل الجدول بدون أسماء');

    $result = $this->service->submit($this->ahmad, $attempt->fresh(), 'نعم صحيح، السؤال ٣ هو الأصعب');

    expect($result['check'])->toBeFalse()->and($result['coach']['ready'])->toBeFalse()->and($result['attempt']->step_index)->toBe(2);
    Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'failed'));
});

it('completes a mission, awards XP once, feeds the passport and badges', function () use ($coach) {
    claudeJson($coach(80), $coach(80), $coach(80), $coach(80));
    $attempt = $this->service->start($this->ahmad, $this->mission);
    foreach (['شو أصعب سؤال؟', 'حلّل الجدول بدون أسماء', 'لا، الأصعب هو السؤال الرابع س4', 'نشاط علاجي بمجموعات'] as $answer) {
        $attempt = $this->service->submit($this->ahmad, $attempt->fresh(), $answer)['attempt'];
    }

    expect($attempt->status)->toBe('completed')->and($attempt->score)->toEqual(80)
        ->and(XpEvent::where('user_id', $this->ahmad->id)->where('reason', 'mission')->sum('amount'))->toEqual(120)
        ->and(Passport::where('user_id', $this->ahmad->id)->first()->competencies)->toHaveKey('data_analysis')
        ->and(UserBadge::where('user_id', $this->ahmad->id)->count())->toBeGreaterThanOrEqual(2); // first_mission + data_analyst

    expect(app(GamificationService::class)->award($this->ahmad, 120, 'mission', "mission:{$attempt->id}"))->toBeFalse();
});

it('redacts protected names before the coach sees a submission', function () use ($coach) {
    app(\App\Services\Institution\ProtectedTermsRepository::class)->add(['رهف']);
    claudeJson($coach(50, false));
    $attempt = $this->service->start($this->ahmad, $this->mission);

    $this->service->submit($this->ahmad, $attempt, 'رهف أخذت أقل علامة');

    Http::assertSent(fn ($r) => ! str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'رهف'));
});
