<?php

namespace App\Services\Media;

interface AsyncMediaGenerator
{
    public function submit(string $prompt, array $options = []): string;

    public function poll(string $externalId): AsyncStatus;

    public function download(string $url): MediaResult;
}
