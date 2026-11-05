<?php

use App\AI\VersionResolver;

it('keeps percentage buckets stable and monotonic as the rollout grows', function () {
    $resolver = new VersionResolver;
    $in10 = collect(range(1, 1000))->filter(fn ($t) => $resolver->inRollout(['mode' => 'percent', 'percent' => 10], 7, $t));
    $in50 = collect(range(1, 1000))->filter(fn ($t) => $resolver->inRollout(['mode' => 'percent', 'percent' => 50], 7, $t));

    expect($in10->count())->toBeGreaterThan(50)->toBeLessThan(150)
        ->and($in10->diff($in50)->isEmpty())->toBeTrue(); // nobody drops out when the percentage increases
});

it('handles pilot, all and unknown modes', function () {
    $resolver = new VersionResolver;

    expect($resolver->inRollout(['mode' => 'pilot', 'tenant_ids' => [3]], 1, 3))->toBeTrue()
        ->and($resolver->inRollout(['mode' => 'pilot', 'tenant_ids' => [3]], 1, 4))->toBeFalse()
        ->and($resolver->inRollout(['mode' => 'all'], 1, 4))->toBeTrue()
        ->and($resolver->inRollout([], 1, 4))->toBeFalse();
});
