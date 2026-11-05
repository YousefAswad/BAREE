<?php

namespace App\Services\Learning;

/** Light Arabic normalisation for matching: diacritics, tatweel, alef/ya/ta-marbuta variants, digits. */
final class ArabicNormalizer
{
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text); // harakat, dagger alef, tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    /** Tokens without very common words, for keyword overlap. */
    public static function tokens(string $text): array
    {
        static $stop = ['في', 'من', 'على', 'الى', 'عن', 'مع', 'هذا', 'هذه', 'ان', 'او', 'و', 'ما', 'هل', 'كيف', 'شو', 'بقدر', 'the', 'a', 'of', 'to', 'and', 'is'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::normalize($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_diff(array_map(fn ($w) => preg_replace('/^(وال|بال|كال|فال|لل|ال)/u', '', $w), $words), $stop));
    }
}
