<?php

namespace App\Services\Learning;

/** Turns the coach model's reply into safe, bounded structured feedback. Never trusts the model's numbers blindly. */
final class CoachResponseParser
{
    /** @return array{scores: array<string,int>, feedback: string, hint: ?string, ready: bool, valid: bool} */
    public function parse(string $raw, array $criteria): array
    {
        $json = null;
        if (preg_match('/\{.*\}/su', $raw, $m)) {
            $json = json_decode($m[0], true);
        }

        if (! is_array($json)) {
            return ['scores' => [], 'feedback' => mb_substr(trim(strip_tags($raw)), 0, 800) ?: 'جرّب مرة ثانية بتفاصيل أكثر.', 'hint' => null, 'ready' => false, 'valid' => false];
        }

        $scores = [];
        foreach ($criteria as $criterion) {
            $value = $json['scores'][$criterion] ?? null;
            if (is_numeric($value)) {
                $scores[$criterion] = (int) max(0, min(100, round((float) $value)));
            }
        }

        return [
            'scores' => $scores,
            'feedback' => mb_substr(trim((string) ($json['feedback'] ?? '')), 0, 1200),
            'hint' => isset($json['hint']) ? mb_substr(trim((string) $json['hint']), 0, 400) : null,
            'ready' => (bool) ($json['ready'] ?? false),
            'valid' => true,
        ];
    }
}
