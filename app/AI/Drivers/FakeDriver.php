<?php

namespace App\AI\Drivers;

use App\AI\Contracts\LlmDriver;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;

/** Deterministic driver for local development, demos and CI. */
final class FakeDriver implements LlmDriver
{
    public array $received = [];

    public function __construct(private string $reply = 'تجربة: هذا رد تجريبي من BAREE.') {}

    public function providerKey(): string
    {
        return 'fake';
    }

    public function send(LlmRequest $request): LlmResponse
    {
        $this->received[] = $request;

        return new LlmResponse($this->reply, 100, 50, 'fake', $request->model, 'end_turn');
    }
}
