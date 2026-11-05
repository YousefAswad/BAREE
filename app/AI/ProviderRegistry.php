<?php

namespace App\AI;

use App\AI\Contracts\LlmDriver;
use App\AI\Drivers\AnthropicDriver;
use App\AI\Drivers\FakeDriver;
use App\AI\Drivers\OpenAiCompatibleDriver;
use App\AI\Exceptions\ProviderUnavailable;
use App\Models\AiProvider;
use App\Models\AiProviderCredential;

/**
 * Builds a driver for a provider key. Credentials: institution "bring your own key" first,
 * then the platform key stored encrypted in the database, then the environment.
 */
final class ProviderRegistry
{
    /** @var array<string, LlmDriver> test/dev overrides */
    private array $overrides = [];

    public function override(string $key, LlmDriver $driver): void
    {
        $this->overrides[$key] = $driver;
    }

    public function driver(string $providerKey, ?int $tenantId): LlmDriver
    {
        if (isset($this->overrides[$providerKey])) {
            return $this->overrides[$providerKey];
        }

        $provider = AiProvider::query()->where('key', $providerKey)->where('enabled', true)->first()
            ?? throw new ProviderUnavailable("Provider {$providerKey} is disabled.");

        $secret = AiProviderCredential::query()->where('provider_id', $provider->id)->where('tenant_id', $tenantId)->value('secret')
            ?? AiProviderCredential::query()->where('provider_id', $provider->id)->whereNull('tenant_id')->value('secret')
            ?? config("ai_gateway.providers.{$providerKey}.key");

        return match ($provider->driver) {
            'anthropic' => new AnthropicDriver(
                apiKey: (string) $secret,
                baseUrl: $provider->base_url ?: 'https://api.anthropic.com',
                apiVersion: (string) config('ai_gateway.providers.anthropic.api_version', '2023-06-01'),
            ),
            'openai_compatible' => new OpenAiCompatibleDriver($provider->key, $secret, $provider->base_url ?: 'https://api.openai.com/v1'),
            'fake' => new FakeDriver,
            default => throw new ProviderUnavailable("Unknown driver {$provider->driver}."),
        };
    }

    public function modelFor(string $providerKey, string $tier): string
    {
        return (string) (config("ai_gateway.providers.{$providerKey}.models.{$tier}")
            ?? config("ai_gateway.providers.{$providerKey}.models.standard"));
    }
}
