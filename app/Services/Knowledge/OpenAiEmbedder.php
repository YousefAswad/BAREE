<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;

final class OpenAiEmbedder implements Embedder
{
    public function __construct(private string $apiKey, private string $model, private int $dimensions) {}

    public function embed(array $texts): array
    {
        $vectors = [];
        foreach (array_chunk($texts, 64) as $batch) {
            $response = Http::withToken($this->apiKey)->timeout(60)->retry(2, 1000, throw: false)
                ->post('https://api.openai.com/v1/embeddings', ['model' => $this->model, 'input' => $batch, 'dimensions' => $this->dimensions])->throw();
            foreach (collect($response->json('data'))->sortBy('index') as $row) {
                $vectors[] = $row['embedding'];
            }
        }

        return $vectors;
    }
}
