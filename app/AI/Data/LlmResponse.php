<?php

namespace App\AI\Data;

final readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public string $provider,
        public string $model,
        public ?string $stopReason = null,
    ) {}
}
