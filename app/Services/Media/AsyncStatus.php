<?php

namespace App\Services\Media;

final readonly class AsyncStatus
{
    public function __construct(public string $state, public ?string $resultUrl = null, public ?string $error = null) {}
}
