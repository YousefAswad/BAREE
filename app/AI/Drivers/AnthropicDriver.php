<?php

namespace App\AI\Drivers;

use App\AI\Contracts\StreamingLlmDriver;
use App\AI\Support\SseParser;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\ProviderUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Claude Messages API: POST {base}/v1/messages with x-api-key and anthropic-version headers. */
final class AnthropicDriver implements StreamingLlmDriver
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://api.anthropic.com',
        private string $apiVersion = '2023-06-01',
        private int $timeout = 120,
    ) {}

    public function providerKey(): string
    {
        return 'anthropic';
    }

    public function send(LlmRequest $request): LlmResponse
    {
        $payload = array_filter([
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'system' => $request->system,
            'messages' => $request->messages,
            'temperature' => $request->temperature,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => $this->apiVersion])
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry(2, 750, fn ($e) => $e instanceof ConnectionException
                    || in_array(optional($e->response ?? null)->status(), [429, 529], true), throw: false)
                ->post('/v1/messages', $payload);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable('Anthropic unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || in_array($response->status(), [429, 529], true)) {
            throw new ProviderUnavailable('Anthropic returned '.$response->status());
        }

        $response->throw(); // 4xx = our bug or bad key: surface it, do not silently fail over

        $text = collect($response->json('content', []))
            ->where('type', 'text')->pluck('text')->implode('');

        return new LlmResponse(
            text: $text,
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            provider: $this->providerKey(),
            model: (string) $response->json('model', $request->model),
            stopReason: $response->json('stop_reason'),
        );
    }

    public function stream(LlmRequest $request, callable $onDelta): LlmResponse
    {
        $payload = array_filter([
            'model' => $request->model, 'max_tokens' => $request->maxTokens, 'system' => $request->system,
            'messages' => $request->messages, 'temperature' => $request->temperature, 'stream' => true,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => $this->apiVersion, 'accept' => 'text/event-stream'])
                ->withOptions(['stream' => true])->timeout($this->timeout)
                ->post('/v1/messages', $payload);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable('Anthropic unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || in_array($response->status(), [429, 529], true)) {
            throw new ProviderUnavailable('Anthropic returned '.$response->status());
        }
        $response->throw();

        // Some gateways ignore "stream": answer as a normal JSON message and emit it as one delta.
        if (str_contains((string) $response->header('Content-Type'), 'application/json')) {
            $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');
            $onDelta($text);

            return new LlmResponse($text, (int) $response->json('usage.input_tokens', 0), (int) $response->json('usage.output_tokens', 0),
                $this->providerKey(), (string) $response->json('model', $request->model), $response->json('stop_reason'));
        }

        $parser = new SseParser;
        $body = $response->toPsrResponse()->getBody();
        $text = '';
        $usage = ['in' => 0, 'out' => 0];
        $stop = null;
        $model = $request->model;

        $handle = function (string $data) use (&$text, &$usage, &$stop, &$model, $onDelta) {
            $event = json_decode($data, true);
            if (! is_array($event)) {
                return;
            }
            switch ($event['type'] ?? null) {
                case 'message_start':
                    $usage['in'] = (int) data_get($event, 'message.usage.input_tokens', 0);
                    $model = (string) data_get($event, 'message.model', $model);
                    break;
                case 'content_block_delta':
                    if (data_get($event, 'delta.type') === 'text_delta') {
                        $piece = (string) data_get($event, 'delta.text', '');
                        $text .= $piece;
                        $onDelta($piece);
                    }
                    break;
                case 'message_delta':
                    $usage['out'] = (int) data_get($event, 'usage.output_tokens', $usage['out']);
                    $stop = data_get($event, 'delta.stop_reason', $stop);
                    break;
                case 'error':
                    throw new ProviderUnavailable('Anthropic stream error: '.data_get($event, 'error.type', 'unknown'));
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

        return new LlmResponse($text, $usage['in'], $usage['out'], $this->providerKey(), $model, $stop);
    }
}
