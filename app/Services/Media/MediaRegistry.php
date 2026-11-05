<?php

namespace App\Services\Media;

use App\Services\Media\Drivers\AsyncHttpDriver;
use App\Services\Media\Drivers\ElevenLabsSpeechDriver;
use App\Services\Media\Drivers\FakeMediaDriver;
use App\Services\Media\Drivers\OpenAiImageDriver;
use App\Services\Media\Drivers\OpenAiSpeechDriver;
use InvalidArgumentException;

final class MediaRegistry
{
    private array $overrides = [];

    public function override(string $type, object $driver): void
    {
        $this->overrides[$type] = $driver;
    }

    public function driverName(string $type): string
    {
        return isset($this->overrides[$type]) ? 'override' : (string) config("media.{$type}.driver");
    }

    public function image(): ImageGenerator
    {
        return $this->overrides['image'] ?? match (config('media.image.driver')) {
            'openai' => new OpenAiImageDriver((string) config('ai_gateway.providers.openai.key'), (string) config('media.image.model'), (string) config('media.image.size')),
            'fake' => new FakeMediaDriver,
            default => throw new InvalidArgumentException('Unknown image driver.'),
        };
    }

    public function speech(): SpeechGenerator
    {
        return $this->overrides['speech'] ?? match (config('media.speech.driver')) {
            'openai' => new OpenAiSpeechDriver((string) config('ai_gateway.providers.openai.key'), (string) config('media.speech.openai.model'), (string) config('media.speech.openai.voice')),
            'elevenlabs' => new ElevenLabsSpeechDriver((string) config('media.speech.elevenlabs.key'), (string) config('media.speech.elevenlabs.voice_id'), config('media.speech.elevenlabs.model')),
            'fake' => new FakeMediaDriver,
            default => throw new InvalidArgumentException('Unknown speech driver.'),
        };
    }

    public function async(string $type): AsyncMediaGenerator
    {
        if (isset($this->overrides[$type])) {
            return $this->overrides[$type];
        }

        return match (config("media.{$type}.driver")) {
            'async_http' => new AsyncHttpDriver(config("media.{$type}.async_http"), $type === 'video' ? 'video/mp4' : 'model/gltf-binary', $type === 'video' ? 'mp4' : 'glb'),
            'fake' => new FakeMediaDriver,
            default => throw new InvalidArgumentException("Unknown {$type} driver."),
        };
    }
}
