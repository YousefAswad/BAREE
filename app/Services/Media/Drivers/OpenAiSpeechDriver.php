<?php

namespace App\Services\Media\Drivers;

use App\Services\Media\MediaResult;
use App\Services\Media\SpeechGenerator;
use Illuminate\Support\Facades\Http;

final class OpenAiSpeechDriver implements SpeechGenerator
{
    public function __construct(private string $apiKey, private string $model, private string $voice, private string $baseUrl = 'https://api.openai.com/v1') {}

    public function synthesize(string $text, array $options = []): MediaResult
    {
        $response = Http::withToken($this->apiKey)->timeout(120)
            ->post(rtrim($this->baseUrl, '/').'/audio/speech', ['model' => $this->model, 'voice' => $options['voice'] ?? $this->voice, 'input' => $text, 'response_format' => 'mp3'])
            ->throw();

        return new MediaResult($response->body(), 'audio/mpeg', 'mp3');
    }
}
