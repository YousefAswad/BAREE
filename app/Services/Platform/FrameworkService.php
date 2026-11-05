<?php

namespace App\Services\Platform;

use App\Domain\Assessment\ScoringEngine;
use App\Models\CompetencyFramework;
use App\Models\Passport;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Weights, thresholds and level rules: validated, previewed against real passports, then saved. */
final class FrameworkService
{
    public function __construct(private ScoringEngine $engine, private TenantContext $context) {}

    /**
     * @param  array<string, float>  $weights  competency key => weight
     * @param  array<int, array{rank:int, min_score:float, requirements:?array}>  $levels
     */
    public function preview(CompetencyFramework $framework, array $weights, array $levels): array
    {
        $this->validate($framework, $weights, $levels);
        $framework->loadMissing(['competencies', 'levels']);

        $current = $this->distribution($framework, $framework->competencies->pluck('weight', 'key')->all(),
            $framework->levels->map(fn ($l) => ['rank' => $l->rank, 'min_score' => $l->min_score, 'requirements' => $l->requirements])->all());
        $proposed = $this->distribution($framework, $weights, $levels);

        return ['current' => $current, 'proposed' => $proposed, 'sample' => array_sum($current)];
    }

    public function save(CompetencyFramework $framework, array $weights, array $levels, string $reason): void
    {
        $this->validate($framework, $weights, $levels);

        DB::transaction(function () use ($framework, $weights, $levels) {
            foreach ($framework->competencies as $competency) {
                $competency->update(['weight' => $weights[$competency->key]]);
            }
            foreach ($levels as $level) {
                $framework->levels()->where('rank', $level['rank'])->update([
                    'min_score' => $level['min_score'], 'requirements' => json_encode($level['requirements'] ?? null),
                ]);
            }
            $framework->increment('version');
        });

        Audit::log('framework.updated', $framework, ['reason' => $reason, 'version' => $framework->version]);
    }

    private function validate(CompetencyFramework $framework, array $weights, array $levels): void
    {
        $keys = $framework->competencies()->pluck('key')->all();

        if (array_diff($keys, array_keys($weights)) !== [] || array_diff(array_keys($weights), $keys) !== []) {
            throw new DomainException('Weights must cover exactly the framework competencies.');
        }
        if (collect($weights)->contains(fn ($w) => $w < 0 || $w > 100) || array_sum($weights) <= 0) {
            throw new DomainException('Weights must be between 0 and 100 and not all zero.');
        }

        usort($levels, fn ($a, $b) => $a['rank'] <=> $b['rank']);
        if (($levels[0]['min_score'] ?? null) != 0) {
            throw new DomainException('The first level must start at 0.');
        }
        foreach ($levels as $i => $level) {
            if ($i > 0 && $level['min_score'] <= $levels[$i - 1]['min_score']) {
                throw new DomainException('Level thresholds must increase strictly.');
            }
            foreach (array_keys($level['requirements']['min_competency'] ?? []) as $key) {
                if (! in_array($key, $keys, true)) {
                    throw new DomainException("Unknown competency in requirements: {$key}");
                }
            }
        }
    }

    /** Re-scores every existing passport across all institutions (aggregate counts only). */
    private function distribution(CompetencyFramework $framework, array $weights, array $levels): array
    {
        $competencies = $framework->competencies->mapWithKeys(fn ($c) => [$c->id => ['key' => $c->key, 'weight' => (float) $weights[$c->key]]])->all();
        $byKey = $framework->competencies->pluck('id', 'key');
        $levelDefs = collect($levels)->map(fn ($l) => ['id' => $l['rank'], 'rank' => $l['rank'], 'key' => (string) $l['rank'], 'min_score' => (float) $l['min_score'], 'requirements' => $l['requirements'] ?? null])->all();
        $counts = array_fill_keys(array_column($levels, 'rank'), 0);

        // NB: a plain closure, not an arrow fn: arrow fns capture by value, so counts incremented inside would be lost.
        $this->context->asPlatform(function () use ($competencies, $byKey, $levelDefs, &$counts) {
            Passport::query()->select(['id', 'competencies'])->chunkById(1000, function ($passports) use ($competencies, $byKey, $levelDefs, &$counts) {
            foreach ($passports as $passport) {
                $answers = collect($passport->competencies ?? [])->filter(fn ($v, $k) => isset($byKey[$k]))
                    ->map(fn ($v, $k) => ['competency_id' => $byKey[$k], 'score' => (float) $v, 'max_score' => 100, 'weight' => 1, 'confidence' => null])->values()->all();
                $rank = $this->engine->score($competencies, $answers, $levelDefs)->levelId;
                if ($rank !== null) {
                    $counts[$rank]++;
                }
            }
            });
        });

        return $counts;
    }
}
