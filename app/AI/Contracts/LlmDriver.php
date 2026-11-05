<?php

namespace App\AI\Contracts;

use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;

interface LlmDriver
{
    public function providerKey(): string;

    /** @throws \App\AI\Exceptions\ProviderUnavailable when the call should fail over to the next provider */
    public function send(LlmRequest $request): LlmResponse;
}
