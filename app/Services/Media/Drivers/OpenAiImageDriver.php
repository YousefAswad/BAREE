<?php

namespace App\Services\Media\Drivers;

use App\Services\Media\ImageGenerator;
use App\Services\Media\MediaResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiImageDriver implements ImageGenerator
{
    public function __construct(private string $apiKey, private string $model, private string $size = '1024x1024', private string $baseUrl = 'https://api.openai.com/v1') {}

    public function generate(string $prompt, array $options = []): MediaResult
    {
        $response = Http::withToken($this->apiKey)->timeout(180)->retry(2, 1000, throw: false)
            ->post(rtrim($this->baseUrl, '/').'/images/generations', ['model' => $this->model, 'prompt' => $prompt, 'size' => $options['size'] ?? $this->size, 'n' => 1])
            ->throw();

        $b64 = $response->json('data.0.b64_json');
        if (! $b64) {
            throw new RuntimeException('Image provider returned no image data.');
        }

        return new MediaResult(base64_decode($b64, true) ?: throw new RuntimeException('Invalid image data.'), 'image/png', 'png');
    }
}
