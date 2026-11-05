<?php

namespace App\AI\Privacy;

/**
 * Reversible redaction before any text leaves BAREE for a model provider.
 * Names come from the institution's protected list (e.g. student roster); patterns cover
 * e-mails, phone numbers and national IDs. The map is stored encrypted and used only to
 * restore the output for the requesting user.
 */
final class PiiRedactor
{
    private const PATTERNS = [
        'EMAIL' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
        'PHONE' => '/(?<![\d\p{Nd}])(?:\+?\d{1,3}[\s\-]?)?(?:0?5\d)[\s\-]?\d{3}[\s\-]?\d{4}(?![\d\p{Nd}])/u',
        'ID' => '/(?<![\d\p{Nd}])\d{9}(?![\d\p{Nd}])/u',
    ];

    /** @return array{text:string, map:array<string,string>, count:int} */
    public function redact(string $text, array $protectedNames = [], array $map = []): array
    {
        $counters = [];
        foreach ($map as $token => $value) {
            $type = explode('_', trim($token, '[]'))[0];
            $counters[$type] = max($counters[$type] ?? 0, (int) substr(strrchr(trim($token, '[]'), '_'), 1));
        }
        $count = 0;

        $replace = function (string $type, string $value) use (&$map, &$counters, &$count): string {
            $existing = array_search($value, $map, true);
            if ($existing !== false) {
                $count++;

                return $existing;
            }
            $counters[$type] = ($counters[$type] ?? 0) + 1;
            $token = '['.$type.'_'.$counters[$type].']';
            $map[$token] = $value;
            $count++;

            return $token;
        };

        foreach (self::PATTERNS as $type => $pattern) {
            $text = preg_replace_callback($pattern, fn ($m) => $replace($type, $m[0]), $text);
        }

        // Longest names first so "محمد علي" wins over "محمد". Unicode letter boundaries keep
        // "سلما" from matching inside "السلماوي", while one attached Arabic proclitic
        // (و ف ب ل ك) is allowed and preserved: "ويوسف" becomes "و[PERSON_1]".
        // This can over-redact (e.g. "ريم" inside "كريم"), which is the safe direction.
        usort($protectedNames, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach (array_filter(array_map('trim', $protectedNames)) as $name) {
            $pattern = '/(?<!\p{L})([وفبلك]?)'.preg_quote($name, '/').'(?!\p{L})/u';
            $text = preg_replace_callback($pattern, fn ($m) => $m[1].$replace('PERSON', mb_substr($m[0], mb_strlen($m[1]))), $text);
        }

        return ['text' => $text, 'map' => $map, 'count' => $count];
    }

    public function restore(string $text, array $map): string
    {
        return strtr($text, $map);
    }
}
