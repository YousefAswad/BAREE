<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use App\Jobs\RunAgentJob;
use App\Models\AiRun;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgentRunController extends Controller
{
    public function store(Request $request, string $agentKey): JsonResponse
    {
        $this->authorize('ai.agents.use');

        $data = $request->validate([
            'inputs' => ['required', 'array', 'max:40'],
            'workspace_id' => ['nullable', 'integer'],
            'task_instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $workspace = isset($data['workspace_id']) ? Workspace::query()->findOrFail($data['workspace_id']) : null;
        abort_if($workspace && $workspace->owner_id !== $request->user()->id, 403);

        RunAgentJob::dispatch($request->user()->id, $agentKey, $data['inputs'], $workspace?->id, $data['task_instructions'] ?? null);

        return response()->json(['status' => 'queued'], 202);
    }

    public function show(Request $request, AiRun $run, AiGateway $gateway): JsonResponse
    {
        abort_unless($run->user_id === $request->user()->id, 403);

        return response()->json([
            'id' => $run->public_id,
            'status' => $run->status,
            'output' => $run->status === 'succeeded' ? $gateway->presentOutput($run, $request->user()) : null,
            'redactions' => $run->redactions_count,
            'credits' => $run->credits,
        ]);
    }
}
