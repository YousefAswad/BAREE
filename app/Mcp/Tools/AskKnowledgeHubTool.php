<?php

namespace App\Mcp\Tools;

use App\Services\Knowledge\HubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Answers questions about the user\'s institution policies and guides, citing the approved documents it used.')]
class AskKnowledgeHubTool extends Tool
{
    use McpAuth;

    public function handle(Request $request): Response
    {
        $hub = app(HubService::class);
        $user = $this->mcpUser();
        $question = $request->validate(['question' => 'required|string|max:1000'])['question'];
        $result = $hub->ask($user, $question);

        $sources = collect($result['citations'])->map(fn ($c) => "[{$c['n']}] ".($c['title']['ar'] ?? ''))->implode("\n");

        return Response::text($result['answer'].($sources ? "\n\nالمصادر:\n".$sources : ''));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['question' => $schema->string()->description('The question, in Arabic or English')->required()];
    }
}
