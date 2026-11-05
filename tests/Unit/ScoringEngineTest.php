<?php

use App\Domain\Assessment\ScoringEngine;

$competencies = [1 => ['key' => 'prompting', 'weight' => 2.0], 2 => ['key' => 'responsible_ai', 'weight' => 1.0], 3 => ['key' => 'data', 'weight' => 1.0]];
$levels = [
    ['id' => 10, 'rank' => 1, 'key' => 'explorer', 'min_score' => 0, 'requirements' => null],
    ['id' => 20, 'rank' => 2, 'key' => 'creator', 'min_score' => 60, 'requirements' => null],
    ['id' => 30, 'rank' => 3, 'key' => 'champion', 'min_score' => 85, 'requirements' => ['min_competency' => ['responsible_ai' => 70]]],
];
$answer = fn (int $c, float $s, ?int $conf = null) => ['competency_id' => $c, 'score' => $s, 'max_score' => 3, 'weight' => 1, 'confidence' => $conf];

it('weights competencies and renormalises when one is not measured', function () use ($competencies, $levels, $answer) {
    $result = (new ScoringEngine)->score($competencies, [$answer(1, 3), $answer(1, 3), $answer(2, 0)], $levels);

    expect($result->competencies)->toBe(['prompting' => 100.0, 'responsible_ai' => 0.0])
        ->and($result->overall)->toBe(66.67) // (2*100 + 1*0) / 3; "data" excluded
        ->and($result->levelKey)->toBe('creator');
});

it('blocks a level when a minimum competency is not met, and explains why', function () use ($competencies, $levels, $answer) {
    $result = (new ScoringEngine)->score($competencies, [$answer(1, 3), $answer(1, 3), $answer(3, 3), $answer(2, 2)], $levels);

    expect($result->overall)->toBeGreaterThan(85)
        ->and($result->levelKey)->toBe('creator')
        ->and($result->next['key'])->toBe('champion')
        ->and($result->next['blockers'][0])->toMatchArray(['competency' => 'responsible_ai', 'required' => 70]);
});

it('measures confidence calibration without changing the score', function () use ($competencies, $levels, $answer) {
    $result = (new ScoringEngine)->score($competencies, [$answer(1, 0, 4), $answer(1, 3, 1), $answer(2, 3, 3)], $levels);

    expect($result->calibration)->toMatchArray(['overconfident' => 1, 'underconfident' => 1, 'rated' => 3])
        ->and($result->competencies['prompting'])->toBe(50.0);
});
