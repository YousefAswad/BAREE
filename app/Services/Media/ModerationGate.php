<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks media prompts before anything is generated. Always runs local rules; optionally the provider's
 * moderation endpoint. If the remote check fails, generation is blocked (fail closed) for image/video/3D.
 */
final class ModerationGate
{
    /** Educational platform for schools: depictions of real, identifiable minors are never generated. */
    private const LOCAL_PATTERNS = [
        '/\b(nude|naked|sexual|porn|nsfw)\b/iu',
        '/(عاري|عارية|إباحي|جنسي)/u',
        '/(صورة|صور|فيديو)\s+(حقيقية\s+)?(لـ?|عن|ل)?\s*(الطالب|الطالبة|طالب|طالبة|طلابي|تلميذ)/u',
        '/\b(photo|picture|video)\s+of\s+(my\s+)?(student|pupil)s?\b/iu',
        '/\[PERSON_\d+\]/u', // a redacted real person inside a media prompt
    ];

    public function check(string $prompt, string $type): void
    {
        foreach (self::LOCAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $prompt)) {
                throw new MediaBlocked(__('This request cannot be generated. Media must not depict real students or unsafe content.'));
            }
        }

        if (config('media.moderation.driver') !== 'openai' || ! config('ai_gateway.providers.openai.key')) {
            return;
        }

        try {
            $flagged = Http::withToken((string) config('ai_gateway.providers.openai.key'))->timeout(20)
                ->post('https://api.openai.com/v1/moderations', ['model' => config('media.moderation.model'), 'input' => $prompt])
                ->throw()->json('results.0.flagged');
        } catch (Throwable $e) {
            report($e);
            if ($type !== 'speech') {
                throw new MediaBlocked(__('Safety check unavailable, please try again shortly.'));
            }

            return;
        }

        if ($flagged) {
            throw new MediaBlocked(__('This request was blocked by the safety check.'));
        }
    }
}
