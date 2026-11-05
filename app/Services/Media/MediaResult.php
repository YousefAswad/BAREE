<?php

namespace App\Services\Media;

final readonly class MediaResult
{
    public function __construct(public string $bytes, public string $mime, public string $extension) {}
}
