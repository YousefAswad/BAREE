<?php

use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\BareeSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function studioSetup(): User
{
    test()->seed(BareeSeeder::class);
    Storage::fake('local');
    config([
        'filesystems.default' => 'local',
        'baree.uploads.scan_with' => 'none',
        'media.moderation.driver' => 'local',
        'broadcasting.default' => 'null',
        'ai_gateway.providers.anthropic.key' => 'test',
        'ai_gateway.providers.openai.key' => 'test',
        'ai_gateway.providers.openai.models.standard' => 'openai-test',
    ]);
    $ahmad = User::where('email', 'ahmad@future.test')->first();
    bareeTenantContext()->set($ahmad->tenant_id);

    return $ahmad;
}

function claudeReplies(string ...$texts): void
{
    $responses = array_map(fn ($t) => Http::response(['model' => 'claude-sonnet-5-5', 'content' => [['type' => 'text', 'text' => $t]],
        'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]]), $texts);
    Http::fake(['api.anthropic.com/*' => Http::sequence($responses)->whenEmpty(Http::response(['content' => [['type' => 'text', 'text' => 'ok']], 'usage' => []]))]);
}

function workspaceFor(User $user, string $title = 'رياضيات ٨'): Workspace
{
    return app(\App\Services\Studio\WorkspaceService::class)->create($user, ['kind' => 'course', 'title' => $title]);
}
