<?php

namespace App\Services\Export;

/** Shared footer for every exported file: AI use is always disclosed. */
final class Renderers
{
    public static function disclosure(string $reviewer): string
    {
        return 'أُنشئ بمساعدة الذكاء الاصطناعي عبر BAREE وراجعه: '.$reviewer;
    }
}
