<?php

namespace App\Services\Media\Drivers;

use App\Services\Media\MediaResult;
use App\Services\Media\SpeechGenerator;
use Illuminate\Support\Facades\Http;

/** Strong Arabic voices; voice_id and model are configured per installation. */
final class ElevenLabsSpeechDriver implements SpeechGenerator
{
    public function __construct(private string $apiKey, private string $voiceId, private ?string $model = null) {}

    public function synthesize(string $text, array $options = []): MediaResult
    {
        $voice = $options['voice'] ?? $this->voiceId;
        $response = Http::withHeaders(['xi-api-key' => $this->apiKey, 'accept' => 'audio/mpeg'])->timeout(120)
            ->post('https://api.elevenlabs.io/v1/text-to-speech/'.rawurlencode($voice), array_filter(['text' => $text, 'model_id' => $this->model]))
            ->throw();

        return new MediaResult($response->body(), 'audio/mpeg', 'mp3');
    }
}
