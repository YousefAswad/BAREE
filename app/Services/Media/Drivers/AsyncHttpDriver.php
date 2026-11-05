<?php

namespace App\Services\Media\Drivers;

use App\Services\Media\AsyncMediaGenerator;
use App\Services\Media\AsyncStatus;
use App\Services\Media\MediaResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Config-driven "submit → poll → download" driver for video and 3D providers. */
final class AsyncHttpDriver implements AsyncMediaGenerator
{
    public function __construct(private array $config, private string $mime, private string $extension) {}

    public function submit(string $prompt, array $options = []): string
    {
        $response = $this->http()->post($this->config['submit_url'], ['prompt' => $prompt] + $options)->throw();

        return (string) ($response->json($this->config['id_path']) ?? throw new RuntimeException('Provider returned no job id.'));
    }

    public function poll(string $externalId): AsyncStatus
    {
        $response = $this->http()->get(str_replace('{id}', rawurlencode($externalId), $this->config['status_url']))->throw();
        $state = (string) $response->json($this->config['state_path']);

        return match (true) {
            in_array($state, $this->config['done_states'], true) => new AsyncStatus('succeeded', $response->json($this->config['result_path'])),
            in_array($state, $this->config['failed_states'], true) => new AsyncStatus('failed', null, $state),
            default => new AsyncStatus('processing'),
        };
    }

    public function download(string $url): MediaResult
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array($host, $this->config['allowed_hosts'], true)) {
            throw new RuntimeException("Refusing to download from {$host}: not an allowed result host."); // SSRF guard
        }

        $response = Http::timeout(300)->withOptions(['allow_redirects' => false])->get($url)->throw();
        if (strlen($response->body()) > 500 * 1024 * 1024) {
            throw new RuntimeException('Result too large.');
        }

        return new MediaResult($response->body(), $this->mime, $this->extension);
    }

    private function http()
    {
        return Http::acceptJson()->timeout(60)
            ->withHeaders([$this->config['auth_header'] => ($this->config['auth_prefix'] ?? '').$this->config['api_key']]);
    }
}
