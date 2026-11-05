<?php

namespace App\Services\Knowledge;

/** Paragraph-aware chunks with overlap, so a policy clause is not cut in the middle when possible. */
final class Chunker
{
    public function split(string $text, int $size = 1200, int $overlap = 150): array
    {
        $paragraphs = preg_split("/\n\s*\n/u", trim($text)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (mb_strlen($current) + mb_strlen($paragraph) + 2 <= $size) {
                $current = $current === '' ? $paragraph : $current."\n\n".$paragraph;

                continue;
            }
            if ($current !== '') {
                $chunks[] = $current;
                $current = mb_substr($current, -$overlap)."\n\n";
            }
            while (mb_strlen($paragraph) > $size) { // very long paragraph: hard wrap
                $chunks[] = trim($current.mb_substr($paragraph, 0, $size - mb_strlen($current)));
                $paragraph = mb_substr($paragraph, $size - mb_strlen($current) - $overlap);
                $current = '';
            }
            $current .= $paragraph;
        }
        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
