<?php

namespace App\Services\Media\Drivers;

use App\Services\Media\AsyncMediaGenerator;
use App\Services\Media\AsyncStatus;
use App\Services\Media\ImageGenerator;
use App\Services\Media\MediaResult;
use App\Services\Media\SpeechGenerator;

/** Deterministic media for local development, demos and tests. */
final class FakeMediaDriver implements ImageGenerator, SpeechGenerator, AsyncMediaGenerator
{
    public array $prompts = [];

    public int $pollsUntilDone = 1;

    private int $polls = 0;

    public function generate(string $prompt, array $options = []): MediaResult
    {
        $this->prompts[] = $prompt;

        // 1×1 transparent PNG
        return new MediaResult(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='), 'image/png', 'png');
    }

    public function synthesize(string $text, array $options = []): MediaResult
    {
        $this->prompts[] = $text;

        return new MediaResult('ID3fake-mp3', 'audio/mpeg', 'mp3');
    }

    public function submit(string $prompt, array $options = []): string
    {
        $this->prompts[] = $prompt;

        return 'fake-job-'.count($this->prompts);
    }

    public function poll(string $externalId): AsyncStatus
    {
        return ++$this->polls >= $this->pollsUntilDone ? new AsyncStatus('succeeded', 'https://fake.local/result') : new AsyncStatus('processing');
    }

    public function download(string $url): MediaResult
    {
        return new MediaResult('fake-binary', 'application/octet-stream', 'bin');
    }
}
