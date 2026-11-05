<?php

namespace App\AI\Data;

final readonly class LlmRequest
{
    /** @param  array<int, array{role:string, content:string}>  $messages */
    public function __construct(
        public string $system,
        public array $messages,
        public string $model,
        public int $maxTokens = 4000,
        public ?float $temperature = null,
    ) {}
}
