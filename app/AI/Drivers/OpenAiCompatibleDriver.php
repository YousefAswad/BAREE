<?php

namespace App\AI\Drivers;

use App\AI\Contracts\StreamingLlmDriver;
use App\AI\Support\SseParser;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\ProviderUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Chat Completions shape. One driver covers OpenAI and every OpenAI-compatible endpoint
 * (OpenRouter, Groq, Mistral, DeepSeek, Azure OpenAI gateways, local Ollama...).
 */
final class OpenAiCompatibleDriver implements StreamingLlmDriver
{
    public function __construct(
        private string $key,
        private ?string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private int $timeout = 120,
    ) {}

    public function providerKey(): string
    {
        return $this->key;
    }

    public function send(LlmRequest $request): LlmResponse
    {
        $messages = array_merge([['role' => 'system', 'content' => $request->system]], $request->messages);

        try {
            $http = Http::baseUrl(rtrim($this->baseUrl, '/'))->acceptJson()->timeout($this->timeout)
                ->retry(2, 750, fn ($e) => $e instanceof ConnectionException, throw: false);

            if ($this->apiKey) {
                $http = $http->withToken($this->apiKey);
            }

            $response = $http->post('/chat/completions', array_filter([
                'model' => $request->model,
                'messages' => $messages,
                'max_completion_tokens' => $request->maxTokens,
                'temperature' => $request->temperature,
            ], fn ($v) => $v !== null));
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable($this->key.' unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new ProviderUnavailable($this->key.' returned '.$response->status());
        }

        $response->throw();

        return new LlmResponse(
            text: (string) $response->json('choices.0.message.content', ''),
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            provider: $this->key,
            model: (string) $response->json('model', $request->model),
            stopReason: $response->json('choices.0.finish_reason'),
        );
    }

    public function stream(LlmRequest $request, callable $onDelta): LlmResponse
    {
        $messages = array_merge([['role' => 'system', 'content' => $request->system]], $request->messages);

        try {
            $http = Http::baseUrl(rtrim($this->baseUrl, '/'))->withOptions(['stream' => true])->timeout($this->timeout)
                ->withHeaders(['accept' => 'text/event-stream']);
            if ($this->apiKey) {
                $http = $http->withToken($this->apiKey);
            }
            $response = $http->post('/chat/completions', array_filter([
                'model' => $request->model, 'messages' => $messages, 'max_completion_tokens' => $request->maxTokens,
                'temperature' => $request->temperature, 'stream' => true, 'stream_options' => ['include_usage' => true],
            ], fn ($v) => $v !== null));
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable($this->key.' unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new ProviderUnavailable($this->key.' returned '.$response->status());
        }
        $response->throw();

        if (str_contains((string) $response->header('Content-Type'), 'application/json')) {
            $text = (string) $response->json('choices.0.message.content', '');
            $onDelta($text);

            return new LlmResponse($text, (int) $response->json('usage.prompt_tokens', 0), (int) $response->json('usage.completion_tokens', 0),
                $this->key, (string) $response->json('model', $request->model), $response->json('choices.0.finish_reason'));
        }

        $parser = new SseParser;
        $body = $response->toPsrResponse()->getBody();
        $text = '';
        $in = $out = 0;
        $stop = null;
        $model = $request->model;

        $handle = function (string $data) use (&$text, &$in, &$out, &$stop, &$model, $onDelta) {
            if ($data === '[DONE]') {
                return;
            }
            $chunk = json_decode($data, true);
            if (! is_array($chunk)) {
                return;
            }
            $model = (string) ($chunk['model'] ?? $model);
            $piece = (string) data_get($chunk, 'choices.0.delta.content', '');
            if ($piece !== '') {
                $text .= $piece;
                $onDelta($piece);
            }
            $stop = data_get($chunk, 'choices.0.finish_reason') ?? $stop;
            if (isset($chunk['usage'])) {
                $in = (int) ($chunk['usage']['prompt_tokens'] ?? $in);
                $out = (int) ($chunk['usage']['completion_tokens'] ?? $out);
            }
        };

        while (! $body->eof()) {
            foreach ($parser->feed($body->read(8192)) as $data) {
                $handle($data);
            }
        }
        foreach ($parser->flush() as $data) {
            $handle($data);
        }

        return new LlmResponse($text, $in, $out, $this->key, $model, $stop);
    }
}
