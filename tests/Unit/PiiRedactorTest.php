<?php

use App\AI\Privacy\PiiRedactor;

it('redacts names, emails, phones and ids, then restores them', function () {
    $redactor = new PiiRedactor;
    $text = 'سلمى 92، هويتها 401234567، هاتف 0599123456، بريد salma@school.ps. ملاحظة: سلمى بحاجة متابعة.';

    $result = $redactor->redact($text, ['سلمى']);

    expect($result['text'])->not->toContain('سلمى')
        ->not->toContain('401234567')->not->toContain('0599123456')->not->toContain('salma@school.ps')
        ->toContain('[PERSON_1]')
        ->and(substr_count($result['text'], '[PERSON_1]'))->toBe(2)
        ->and($redactor->restore($result['text'], $result['map']))->toBe($text);
});

it('does not redact a protected name inside a longer word', function () {
    $result = (new PiiRedactor)->redact('عائلة السلماوي', ['سلما']);

    expect($result['text'])->toBe('عائلة السلماوي')->and($result['count'])->toBe(0);
});

it('keeps token numbering stable across several fields', function () {
    $redactor = new PiiRedactor;
    $first = $redactor->redact('يوسف', ['يوسف', 'رهف']);
    $second = $redactor->redact('رهف ويوسف', ['يوسف', 'رهف'], $first['map']);

    expect($second['text'])->toBe('[PERSON_2] و[PERSON_1]');
});
