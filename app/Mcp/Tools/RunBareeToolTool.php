<?php

namespace App\Mcp\Tools;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Runs one single-step BAREE tool for the user and returns the result. Uses the user\'s credits and institution rules.')]
class RunBareeToolTool extends Tool
{
    use McpAuth;

    public function handle(Request $request): Response
    {
        $gateway = app(AiGateway::class);
        $user = $this->mcpUser();
        $data = $request->validate(['tool' => 'required|string|max:64', 'inputs' => 'required|array|max:40', 'notes' => 'nullable|string|max:4000']);

        try {
            [, $version] = $gateway->resolve($user, $data['tool']);
            if (! empty($version->workflow)) {
                return Response::text('This tool has several steps; open it in the BAREE studio.');
            }
            $run = $gateway->run($user, $data['tool'], $data['inputs'], null, $data['notes'] ?? null);
        } catch (AiPolicyViolation $e) {
            return Response::text('Not allowed: '.$e->getMessage());
        }

        return Response::text($gateway->presentOutput($run, $user));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tool' => $schema->string()->description('Tool key from list_baree_tools')->required(),
            'inputs' => $schema->object()->description('Values for the tool inputs, keyed by input key')->required(),
            'notes' => $schema->string()->description('Optional instructions for this task'),
        ];
    }
}
