<?php

namespace App\AI\Contracts;

use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;

interface StreamingLlmDriver extends LlmDriver
{
    /** Calls $onDelta(string $text) as tokens arrive and returns the complete response at the end. */
    public function stream(LlmRequest $request, callable $onDelta): LlmResponse;
}
