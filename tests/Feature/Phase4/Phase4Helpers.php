<?php

use App\Models\User;
use Database\Seeders\BareeSeeder;
use Illuminate\Support\Facades\Http;

function learningSetup(): User
{
    config(['knowledge.embeddings.driver' => 'fake', 'broadcasting.default' => 'null', 'ai_gateway.providers.anthropic.key' => 'test', 'knowledge.min_score' => 0.15]);
    app()->forgetInstance(\App\Services\Knowledge\Embedder::class);
    test()->seed(BareeSeeder::class);
    $ahmad = User::where('email', 'ahmad@future.test')->first();
    bareeTenantContext()->set($ahmad->tenant_id);

    return $ahmad;
}

function claudeJson(string ...$texts): void
{
    $responses = array_map(fn ($t) => Http::response(['model' => 'm', 'content' => [['type' => 'text', 'text' => $t]], 'usage' => ['input_tokens' => 5, 'output_tokens' => 5]]), $texts);
    Http::fake(['api.anthropic.com/*' => Http::sequence($responses)]);
}
