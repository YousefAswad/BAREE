<?php

use App\Services\Export\MarkdownDocument;

it('turns markdown into neutral blocks for Word and PowerPoint', function () {
    $blocks = MarkdownDocument::blocks("## الأهداف\n\n- يحل المعادلة\n- يتحقق من الحل\n\n| السؤال | الدرجة |\n|---|---|\n| ١ | ٢ |\n\nفقرة **مهمة**.");

    expect($blocks[0])->toBe(['type' => 'heading', 'level' => 2, 'text' => 'الأهداف'])
        ->and($blocks[1]['items'])->toBe(['يحل المعادلة', 'يتحقق من الحل'])
        ->and($blocks[2]['rows'][1])->toBe(['١', '٢'])
        ->and($blocks[3]['text'])->toBe('فقرة مهمة.');
});

it('escapes raw HTML and unsafe links in shared pages', function () {
    $html = MarkdownDocument::toHtml("<script>alert(1)</script>\n\n[x](javascript:alert(1))");

    expect($html)->not->toContain('<script>')->not->toContain('javascript:');
});
