<?php

use App\AI\Support\SseParser;

it('reassembles events split across arbitrary network chunks', function () {
    $parser = new SseParser;
    $raw = "event: content_block_delta\ndata: {\"a\":1}\n\ndata: {\"b\":2}\n\n";

    $out = [];
    foreach (str_split($raw, 7) as $chunk) {
        $out = array_merge($out, $parser->feed($chunk));
    }

    expect($out)->toBe(['{"a":1}', '{"b":2}']);
});

it('flushes a final event without a trailing blank line and handles CRLF', function () {
    $parser = new SseParser;

    expect($parser->feed("data: one\r\n\r\ndata: [DONE]"))->toBe(['one'])
        ->and($parser->flush())->toBe(['[DONE]']);
});
