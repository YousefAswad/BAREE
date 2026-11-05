<?php

namespace App\Mcp\Tools;

use App\AI\Gateway\AiGateway;
use App\Services\Studio\ToolCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lists the BAREE tools this user can run, with their required inputs.')]
class ListBareeToolsTool extends Tool
{
    use McpAuth;

    public function handle(Request $request): Response
    {
        [$catalog, $gateway] = [app(ToolCatalog::class), app(AiGateway::class)];
        $user = $this->mcpUser();
        $tools = $catalog->forUser($user)->where('availability', 'available')->where('status', 'published')->map(function ($agent) use ($gateway, $user) {
            [, $version] = $gateway->resolve($user, $agent->key);

            return ['key' => $agent->key, 'name' => $agent->name['ar'] ?? $agent->key, 'description' => $agent->description['ar'] ?? null,
                'multi_step' => ! empty($version->workflow),
                'inputs' => collect($version->input_schema['fields'] ?? [])->map(fn ($f) => ['key' => $f['key'], 'type' => $f['type'], 'required' => $f['required'] ?? false,
                    'options' => array_column($f['options'] ?? [], 'value')])->values()];
        })->values();

        return Response::text(json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
