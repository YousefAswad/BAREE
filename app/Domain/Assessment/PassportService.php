<?php

namespace App\Domain\Assessment;

use App\Models\CompetencyFramework;
use App\Models\CompetencyScore;
use App\Models\Passport;
use App\Models\User;

/**
 * The AI Passport is evidence-based, not test-based:
 * latest assessment (60%) blended with recent practical evidence from Lab and Studio (40%).
 */
final class PassportService
{
    public function __construct(private ScoringEngine $engine) {}

    public function refresh(User $user, CompetencyFramework $framework): Passport
    {
        $framework->loadMissing(['competencies', 'levels']);
        $scores = [];

        foreach ($framework->competencies as $competency) {
            $assessment = CompetencyScore::query()
                ->where('user_id', $user->id)->where('competency_id', $competency->id)->where('source', 'assessment')
                ->latest('recorded_at')->value('score');

            $evidence = CompetencyScore::query()
                ->where('user_id', $user->id)->where('competency_id', $competency->id)
                ->whereIn('source', ['lab', 'studio'])->where('recorded_at', '>=', now()->subDays(90))
                ->avg('score');

            if ($assessment === null && $evidence === null) {
                continue;
            }

            $scores[$competency->key] = round($evidence === null ? (float) $assessment
                : ($assessment === null ? (float) $evidence : 0.6 * $assessment + 0.4 * $evidence), 2);
        }

        $weights = $framework->competencies->pluck('weight', 'key');
        $sum = collect($scores)->keys()->sum(fn ($k) => $weights[$k]);
        $readiness = $sum > 0 ? round(collect($scores)->sum(fn ($v, $k) => $v * $weights[$k]) / $sum, 2) : 0;

        $levels = $framework->levels->map(fn ($l) => ['id' => $l->id, 'rank' => $l->rank, 'key' => $l->key, 'min_score' => $l->min_score, 'requirements' => $l->requirements])->all();
        $competencies = $framework->competencies->mapWithKeys(fn ($c) => [$c->id => ['key' => $c->key, 'weight' => $c->weight]])->all();
        $byId = $framework->competencies->pluck('id', 'key');

        // Reuse the engine for level resolution by expressing each competency score as a single answer.
        $result = $this->engine->score($competencies, collect($scores)->map(fn ($v, $k) => [
            'competency_id' => $byId[$k], 'score' => $v, 'max_score' => 100, 'weight' => 1, 'confidence' => null,
        ])->values()->all(), $levels);

        return Passport::updateOrCreate(['user_id' => $user->id], [
            'readiness' => $readiness,
            'level_id' => $result->levelId,
            'competencies' => $scores,
            'next_level' => $result->next,
            'refreshed_at' => now(),
        ]);
    }
}
