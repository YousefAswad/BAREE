<?php

use App\Services\Knowledge\Chunker;
use App\Services\Learning\ArabicNormalizer;
use App\Services\Learning\CoachResponseParser;

it('extracts coach JSON from chatty replies and clamps scores', function () {
    $raw = "أكيد! هاي ملاحظاتي:\n{\"scores\":{\"clarity\":140,\"privacy\":\"55\",\"unknown\":90},\"feedback\":\"ممتاز\",\"hint\":\"راجع العمود الرابع\",\"ready\":true}";

    $parsed = (new CoachResponseParser)->parse($raw, ['clarity', 'privacy', 'verification']);

    expect($parsed['scores'])->toBe(['clarity' => 100, 'privacy' => 55])->and($parsed['ready'])->toBeTrue()->and($parsed['valid'])->toBeTrue();
});

it('falls back safely when the coach reply is not JSON', function () {
    $parsed = (new CoachResponseParser)->parse('<b>جرّب مرة ثانية</b>', ['clarity']);

    expect($parsed['valid'])->toBeFalse()->and($parsed['ready'])->toBeFalse()->and($parsed['feedback'])->toBe('جرّب مرة ثانية');
});

it('normalises Arabic spelling variants for matching', function () {
    expect(ArabicNormalizer::normalize('السُّؤالُ الرَّابِعُ إلى المدرسةِ'))->toBe(ArabicNormalizer::normalize('السؤال الرابع الي المدرسه'))
        ->and(ArabicNormalizer::tokens('سياسة المدرسة في استخدام الذكاء'))->toContain('سياسه')->toContain('مدرسه');
});

it('chunks long documents on paragraph boundaries with overlap', function () {
    $text = implode("\n\n", array_fill(0, 12, str_repeat('بند من السياسة ', 20)));
    $chunks = (new Chunker)->split($text, 600, 80);

    expect(count($chunks))->toBeGreaterThan(3)
        ->and(collect($chunks)->every(fn ($c) => mb_strlen($c) <= 700))->toBeTrue();
});
