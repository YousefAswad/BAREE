<?php

namespace App\Domain\Assessment;

/**
 * Pure, framework-agnostic scoring. Every number here is configurable from the admin portal:
 * competency weights, question weights, level thresholds and level requirements.
 */
final class ScoringEngine
{
    /**
     * @param  array<int, array{key:string, weight:float}>  $competencies  keyed by competency id
     * @param  array<int, array{competency_id:int, score:float, max_score:float, weight:float, confidence:?int}>  $answers
     * @param  array<int, array{id:int, rank:int, key:string, min_score:float, requirements:?array}>  $levels
     */
    public function score(array $competencies, array $answers, array $levels): ScoringResult
    {
        $earned = [];
        $possible = [];
        $calibration = ['overconfident' => 0, 'underconfident' => 0, 'rated' => 0];

        foreach ($answers as $answer) {
            $id = $answer['competency_id'];
            $earned[$id] = ($earned[$id] ?? 0) + $answer['weight'] * $answer['score'];
            $possible[$id] = ($possible[$id] ?? 0) + $answer['weight'] * $answer['max_score'];

            if ($answer['confidence'] !== null) {
                $calibration['rated']++;
                $ratio = $answer['max_score'] > 0 ? $answer['score'] / $answer['max_score'] : 0;
                if ($answer['confidence'] >= 3 && $ratio < 0.5) {
                    $calibration['overconfident']++;
                } elseif ($answer['confidence'] <= 2 && $ratio >= 0.99) {
                    $calibration['underconfident']++;
                }
            }
        }

        $byKey = [];
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($competencies as $id => $competency) {
            if (empty($possible[$id])) {
                continue; // not measured in this attempt: excluded and weights renormalised
            }
            $value = round(100 * $earned[$id] / $possible[$id], 2);
            $byKey[$competency['key']] = $value;
            $weighted += $competency['weight'] * $value;
            $weightSum += $competency['weight'];
        }

        $overall = $weightSum > 0 ? round($weighted / $weightSum, 2) : 0.0;

        usort($levels, fn ($a, $b) => $a['rank'] <=> $b['rank']);
        $achieved = $levels[0] ?? null;
        $next = null;

        foreach ($levels as $level) {
            if ($overall >= $level['min_score'] && $this->blockers($level, $byKey) === []) {
                $achieved = $level;

                continue;
            }
            $next = $level;
            break;
        }

        $nextInfo = $next === null ? null : [
            'level_id' => $next['id'],
            'key' => $next['key'],
            'gap' => max(0, round($next['min_score'] - $overall, 2)),
            'blockers' => $this->blockers($next, $byKey),
        ];

        $calibration['index'] = $calibration['rated'] > 0
            ? round(1 - ($calibration['overconfident'] + $calibration['underconfident']) / $calibration['rated'], 2)
            : null;

        return new ScoringResult($overall, $byKey, $achieved['id'] ?? null, $achieved['key'] ?? null, $nextInfo, $calibration);
    }

    /** Requirements that stop a user from holding a level even when the overall score is high enough. */
    public function blockers(array $level, array $scores): array
    {
        $blockers = [];
        $requirements = $level['requirements'] ?? [];

        foreach ($requirements['min_competency'] ?? [] as $key => $minimum) {
            if (($scores[$key] ?? 0) < $minimum) {
                $blockers[] = ['type' => 'min_competency', 'competency' => $key, 'required' => $minimum, 'current' => $scores[$key] ?? 0];
            }
        }

        if (isset($requirements['no_competency_below'])) {
            foreach ($scores as $key => $value) {
                if ($value < $requirements['no_competency_below']) {
                    $blockers[] = ['type' => 'floor', 'competency' => $key, 'required' => $requirements['no_competency_below'], 'current' => $value];
                }
            }
        }

        return $blockers;
    }
}
