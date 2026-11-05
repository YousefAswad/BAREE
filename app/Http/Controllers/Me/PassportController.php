<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\CompetencyFramework;
use App\Models\CompetencyScore;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The full AI Passport: radar (now vs first assessment), level ladder, blockers, evidence timeline, badges. */
final class PassportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('passport.view_own');
        $user = $request->user();
        $framework = CompetencyFramework::query()->whereNull('tenant_id')->where('is_default', true)->with(['competencies', 'levels'])->firstOrFail();
        $passport = $user->passport()->with('level')->first();
        $first = AssessmentAttempt::query()->where('user_id', $user->id)->where('status', 'scored')->oldest('submitted_at')->first();
        $names = $framework->competencies->pluck('name', 'id');

        return Inertia::render('Passport/Show', [
            'holder' => ['name' => $user->name, 'job_title' => $user->jobTitle?->name, 'department' => $user->department?->name],
            'passport' => $passport ? ['readiness' => $passport->readiness, 'level' => $passport->level?->only(['rank', 'key', 'name', 'color']),
                'competencies' => $passport->competencies ?? [], 'next' => $passport->next_level, 'xp' => $passport->xp, 'refreshed_at' => $passport->refreshed_at] : null,
            'baseline' => $first?->result['competencies'] ?? null,
            'competencies' => $framework->competencies->map->only(['key', 'name']),
            'levels' => $framework->levels->map->only(['rank', 'key', 'name', 'min_score', 'color', 'requirements']),
            'timeline' => CompetencyScore::query()->where('user_id', $user->id)->latest('recorded_at')->limit(15)->get()
                ->map(fn ($s) => ['source' => $s->source, 'competency' => $names[$s->competency_id] ?? null, 'score' => $s->score, 'at' => $s->recorded_at]),
            'badges' => UserBadge::query()->where('user_id', $user->id)->join('badges', 'badges.id', '=', 'user_badges.badge_id')
                ->get(['badges.key', 'badges.name', 'badges.color', 'user_badges.awarded_at']),
            'cpdHours' => (float) Certificate::query()->where('user_id', $user->id)->whereNull('revoked_at')->sum('hours'),
            'assessments' => AssessmentAttempt::query()->where('user_id', $user->id)->where('status', 'scored')->latest('submitted_at')->get(['public_id', 'overall_score', 'submitted_at']),
        ]);
    }
}
