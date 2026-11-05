<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Returns the user\'s own AI Passport: readiness, level, competencies and what blocks the next level.')]
class MyPassportTool extends Tool
{
    use McpAuth;

    public function handle(Request $request): Response
    {
        $user = $this->mcpUser();
        $passport = $user->passport()->with('level')->first();

        return Response::text(json_encode($passport ? [
            'level' => $passport->level?->name, 'readiness' => $passport->readiness, 'competencies' => $passport->competencies,
            'next_level' => $passport->next_level, 'xp' => $passport->xp,
        ] : ['message' => 'No assessment yet.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
